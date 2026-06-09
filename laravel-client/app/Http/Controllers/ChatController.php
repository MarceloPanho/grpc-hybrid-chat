<?php

namespace App\Http\Controllers;

use App\Services\GrpcChatService;
use Illuminate\Http\Request;

/**
 * ChatController — tela principal e envio de mensagens.
 *
 * O envio usa o padrão BIDIRECIONAL (GrpcChatService::sendMessage), porém de
 * forma curta por causa do modelo stateless do PHP (ver comentário no Service).
 */
class ChatController extends Controller
{
    public function __construct(private GrpcChatService $grpc)
    {
    }

    /** Renderiza a tela de chat. */
    public function mostrarChat(Request $request)
    {
        $username = $request->session()->get('username');
        $userId   = $request->session()->get('user_id');

        // Rótulo do painel "padrão gRPC ativo" — o envio usa Bidirecional.
        $ultimoPadrao = 'Bidirecional';

        return view('chat', compact('username', 'userId', 'ultimoPadrao'));
    }

    /** Envia uma mensagem (Bidirecional curto) e responde JSON. */
    public function enviarMensagem(Request $request)
    {
        $dados = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $userId   = $request->session()->get('user_id');
        $username = $request->session()->get('username');

        try {
            $this->grpc->sendMessage($userId, $username, $dados['content']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erro' => $e->getMessage()], 500);
        }

        // A mensagem volta pro browser pela conexão SSE (/stream), não por aqui.
        return response()->json(['ok' => true]);
    }
}
