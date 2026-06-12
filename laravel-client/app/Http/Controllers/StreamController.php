<?php

namespace App\Http\Controllers;

use App\Services\GrpcChatService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * StreamController — conexão SSE de longa duração (Server-Sent Events).
 *
 * Aqui o Server Streaming do gRPC encontra o SSE do browser: mantemos uma
 * resposta aberta (StreamedResponse) e vamos "empurrando" novas mensagens
 * para o navegador conforme elas aparecem no histórico do servidor Node.
 *
 * Detalhe importante do SSE:
 *   - Cada evento é texto no formato:  "data: {json}\n\n"
 *   - É preciso esvaziar o buffer a cada envio com ob_flush() + flush(),
 *     senão o PHP/Nginx acumulam a saída e nada chega em tempo real.
 *   - O Nginx já está configurado para NÃO bufferizar /stream.
 */
class StreamController extends Controller
{
    public function __construct(private GrpcChatService $grpc)
    {
    }

    /** Abre o canal SSE e empurra novas mensagens. */
    public function conectar(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () {
            // Quantas mensagens já foram enviadas ao browser (índice de corte).
            // Começa em 0: na conexão envia o histórico inteiro e, a partir daí,
            // só as novas — assim a tela popula sozinha apenas pela SSE.
            $jaEnviadas = 0;

            while (true) {
                if (connection_aborted()) {
                    break;
                }

                // Faz a ponte Server Streaming (gRPC) → SSE: a cada ciclo lê o
                // histórico atual do Node e empurra o que ainda não foi enviado.
                $mensagens = $this->grpc->getHistory();

                for ($i = $jaEnviadas; $i < count($mensagens); $i++) {
                    echo 'data: ' . json_encode($mensagens[$i]) . "\n\n";
                }
                $jaEnviadas = count($mensagens);

                // Heartbeat: comentário SSE (linha iniciada por ":") que o browser
                // ignora, mas que GARANTE uma escrita real no socket a cada ciclo.
                // Sem isso, quando o chat está parado nada é enviado, o PHP nunca
                // tenta escrever e connection_aborted() jamais detecta que o cliente
                // saiu — o loop viraria zumbi chamando getHistory() para sempre.
                echo ": ping\n\n";

                // Esvazia os buffers para o evento chegar imediatamente.
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // Após o flush o PHP já tentou escrever no socket: se o cliente
                // desconectou (ex.: clicou em "sair"), agora detectamos e saímos.
                if (connection_aborted()) {
                    break;
                }

                sleep(1); // intervalo de polling
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // Nginx não bufferiza

        return $response;
    }
}
