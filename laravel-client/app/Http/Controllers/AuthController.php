<?php

namespace App\Http\Controllers;

use App\Services\GrpcChatService;
use Illuminate\Http\Request;

/**
 * AuthController — demonstra o padrão UNARY de forma visual.
 *
 *   Formulário de login  → a "requisição"
 *   GrpcChatService::login() → a chamada gRPC unária ao Node
 *   Redirecionamento para /chat → a "resposta"
 */
class AuthController extends Controller
{
    public function __construct(private GrpcChatService $grpc)
    {
        // O Laravel injeta o GrpcChatService automaticamente.
    }

    /** Exibe a tela de login. */
    public function mostrarLogin()
    {
        return view('login');
    }

    /** Processa o login (Unary). */
    public function login(Request $request)
    {
        $dados = $request->validate([
            'username' => 'required|string|max:50',
        ]);

        try {
            // Chamada Unary ao servidor gRPC (Node.js).
            $usuario = $this->grpc->login($dados['username']);
        } catch (\Throwable $e) {
            // Node fora do ar / erro gRPC → registra e mostra tela amigável.
            report($e);

            return view('errors.servico-indisponivel');
        }

        // Sessão preenchida → o middleware VerificarSessao passa a liberar /chat.
        $request->session()->put('user_id', $usuario['user_id']);
        $request->session()->put('username', $usuario['username']);
        $request->session()->put('token', $usuario['token']);

        return redirect('/chat');
    }

    /** Encerra a sessão. */
    public function logout(Request $request)
    {
        $request->session()->flush();

        return redirect('/login');
    }
}
