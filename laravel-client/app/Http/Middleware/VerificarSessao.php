<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerificarSessao
 *
 * Protege as rotas privadas. Só deixa passar quem tem 'user_id' na sessão
 * (preenchido no login Unary). Caso contrário, redireciona para /login.
 */
class VerificarSessao
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sem 'user_id' na sessão (login Unary ainda não feito) → manda pro login.
        if (! $request->session()->has('user_id')) {
            return redirect('/login');
        }

        // Sessão válida: deixa a requisição seguir para a rota protegida.
        return $next($request);
    }
}
