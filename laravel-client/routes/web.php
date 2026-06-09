<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\StreamController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rotas web — grpc-hybrid-chat
|--------------------------------------------------------------------------
| Cada rota indica qual padrão gRPC ela aciona.
*/

// Raiz: manda para o chat (que, sem sessão, cai no login pelo middleware).
Route::get('/', fn () => redirect('/chat'));

// --- Rotas públicas ---------------------------------------------------------
Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');

// POST /login → padrão UNARY (login no servidor gRPC).
Route::post('/login', [AuthController::class, 'login']);

// --- Rotas protegidas (exigem sessão) --------------------------------------
Route::middleware('sessao')->group(function () {
    // Tela principal do chat.
    Route::get('/chat', [ChatController::class, 'mostrarChat']);

    // POST /chat/enviar → padrão BIDIRECIONAL (envio curto, stateless).
    Route::post('/chat/enviar', [ChatController::class, 'enviarMensagem']);

    // GET /historico → padrão SERVER STREAMING (snapshot JSON).
    Route::get('/historico', [HistoryController::class, 'listar']);

    // GET /stream → SSE de longa duração (Server Streaming → browser).
    Route::get('/stream', [StreamController::class, 'conectar']);

    // Logout.
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
