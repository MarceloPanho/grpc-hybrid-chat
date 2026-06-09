@extends('layouts.app')

@section('titulo', 'Login — grpc-hybrid-chat')

@section('conteudo')
<div class="max-w-md mx-auto mt-10">
    <div class="bg-white rounded-xl shadow p-8">
        {{-- Badge do padrão demonstrado nesta tela --}}
        <div class="mb-6 flex justify-center">
            <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-1 rounded-full">
                Padrão: Unary
            </span>
        </div>

        <h2 class="text-2xl font-bold text-center mb-1">Entrar</h2>
        <p class="text-center text-slate-500 text-sm mb-6">
            Escolha um nome de usuário para entrar no chat.
        </p>

        {{-- Exibição de erros de validação / serviço --}}
        @if ($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $erro)
                        <li>{{ $erro }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ url('/login') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="username">
                    Usuário
                </label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    value="{{ old('username') }}"
                    placeholder="ex: marcelo"
                    autofocus
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                >
            </div>

            <button
                type="submit"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 rounded-lg transition"
            >
                Entrar
            </button>
        </form>
    </div>
</div>
@endsection
