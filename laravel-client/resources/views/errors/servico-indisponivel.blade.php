@extends('layouts.app')

@section('titulo', 'Serviço indisponível — grpc-hybrid-chat')

@section('conteudo')
<div class="max-w-lg mx-auto mt-16 text-center">
    <div class="bg-white rounded-xl shadow p-10">
        <div class="text-6xl mb-4">🔌</div>
        <h2 class="text-2xl font-bold mb-2">Serviço temporariamente indisponível</h2>
        <p class="text-slate-500 mb-6">
            Não foi possível falar com o microsserviço de chat (gRPC) no momento.
            Verifique se o servidor Node.js está no ar e tente novamente.
        </p>

        @isset($mensagem)
            <div class="mb-6 bg-amber-50 border border-amber-200 text-amber-700 text-sm rounded-lg px-4 py-3 text-left">
                <span class="font-semibold">Detalhe técnico:</span> {{ $mensagem }}
            </div>
        @endisset

        <a href="{{ url('/login') }}"
           class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-2 rounded-lg transition">
            Voltar ao início
        </a>
    </div>
</div>
@endsection
