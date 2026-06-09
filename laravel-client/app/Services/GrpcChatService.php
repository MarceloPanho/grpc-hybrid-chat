<?php

namespace App\Services;

// Classes geradas pelo protoc a partir de proto/chat.proto (pasta Generated/Chat).

use Chat\UserServiceClient;
use Chat\ChatServiceClient;
use Chat\LoginRequest;
use Chat\ChatMessage;
use Chat\PBEmpty;
use Grpc\ChannelCredentials;

/**
 * GrpcChatService
 *
 * Encapsula TODA a comunicação gRPC com o microsserviço Node.js.
 * Os Controllers nunca falam gRPC diretamente — sempre passam por aqui.
 *
 * Demonstra os três padrões:
 *   - login()       → Unary
 *   - getHistory()  → Server Streaming
 *   - sendMessage() → Bidirecional (com a limitação stateless do PHP, ver abaixo)
 */
class GrpcChatService
{
    private UserServiceClient $userClient;
    private ChatServiceClient $chatClient;

    public function __construct()
    {
        $endereco = env('GRPC_SERVER_HOST') . ':' . env('GRPC_SERVER_PORT');

        $this->userClient = new UserServiceClient($endereco, ['credentials' => ChannelCredentials::createInsecure()]);
        $this->chatClient = new ChatServiceClient($endereco, ['credentials' => ChannelCredentials::createInsecure()]);
    }

    /**
     * Login — padrão UNARY (uma requisição, uma resposta).
     *
     * @return array{user_id:string, username:string, token:string}
     */
    public function login(string $username): array
    {
        $request = new LoginRequest();
        $request->setUsername($username);

        // Unário: ->wait() bloqueia e devolve a tupla [resposta, status].
        [$resposta, $status] = $this->userClient->Login($request)->wait();

        if ($status->code !== \Grpc\STATUS_OK) {
            throw new \RuntimeException(
                'Falha no login gRPC: ' . ($status->details ?: 'erro desconhecido'),
                $status->code
            );
        }

        return [
            'user_id'  => $resposta->getUserId(),
            'username' => $resposta->getUsername(),
            'token'    => $resposta->getToken(),
        ];
    }

    /**
     * getHistory — padrão SERVER STREAMING (uma requisição, várias respostas).
     *
     * @return array<int, array{user_id:string, username:string, content:string, timestamp:int}>
     */
    public function getHistory(): array
    {
        // O message `Empty` do proto vira a classe Chat\PBEmpty no protobuf-PHP.
        $request = new PBEmpty();

        // Server Streaming: uma requisição, várias respostas consumidas via foreach.
        $call = $this->chatClient->GetHistory($request);

        $mensagens = [];
        foreach ($call->responses() as $msg) {
            $mensagens[] = [
                'user_id'   => $msg->getUserId(),
                'username'  => $msg->getUsername(),
                'content'   => $msg->getContent(),
                'timestamp' => (int) $msg->getTimestamp(),
            ];
        }

        return $mensagens;
    }

    /**
     * sendMessage — usa o padrão BIDIRECIONAL, mas de forma curta.
     *
     * ATENÇÃO (limitação do PHP stateless):
     * Diferente do test-client.js (Node.js), que MANTÉM o stream bidirecional
     * aberto trocando mensagens continuamente, aqui cada chamada HTTP do Laravel
     * é efêmera: abrimos o stream Chat, escrevemos UMA mensagem, sinalizamos o
     * fim da escrita (writesDone) e encerramos. O recebimento em tempo real no
     * browser é feito por outro caminho (SSE + GetHistory), não por este stream.
     */
    public function sendMessage(string $userId, string $username, string $content): void
    {
        // Bidirecional curto: abrimos o stream, escrevemos UMA mensagem e encerramos.
        $call = $this->chatClient->Chat();

        $mensagem = new ChatMessage();
        $mensagem->setUserId($userId);
        $mensagem->setUsername($username);
        $mensagem->setContent($content);
        $mensagem->setTimestamp((int) (microtime(true) * 1000)); // milissegundos

        $call->write($mensagem);
        $call->writesDone();

        // Consome uma resposta (o servidor faz o broadcast de volta) para
        // garantir que a escrita foi processada antes de encerrar a chamada HTTP.
        $call->read();
    }
}
