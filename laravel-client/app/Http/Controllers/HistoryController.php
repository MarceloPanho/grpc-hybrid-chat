<?php

namespace App\Http\Controllers;

use App\Services\GrpcChatService;
use Illuminate\Http\Request;

/**
 * HistoryController — snapshot JSON do histórico (sob demanda).
 *
 * Consome o Server Streaming do gRPC (getHistory) e devolve a lista atual
 * como JSON, para o Alpine.js renderizar. Conexão curta (request/response).
 */
class HistoryController extends Controller
{
    public function __construct(private GrpcChatService $grpc)
    {
    }

    /** Retorna o histórico como JSON. */
    public function listar(Request $request)
    {
        // Server Streaming consumido de uma vez e devolvido como snapshot JSON.
        $mensagens = $this->grpc->getHistory();

        return response()->json($mensagens);
    }
}
