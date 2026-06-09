@extends('layouts.app')

@section('titulo', 'Chat — grpc-hybrid-chat')

@section('conteudo')
{{--
    A tela usa Alpine.js (x-data) para:
      - manter a lista de mensagens reativa
      - conectar no SSE (/stream) e receber novas mensagens em tempo real
      - enviar mensagem via fetch para POST /chat/enviar
--}}
<div
    x-data="chat()"
    x-init="iniciar()"
    class="space-y-6"
>
    {{-- ============== SEÇÃO 1: Painel do padrão ativo ============== --}}
    <div class="bg-white rounded-xl shadow p-4 flex items-center justify-between">
        <div>
            <p class="text-xs text-slate-500">Padrão gRPC ativo</p>
            <p class="text-lg font-semibold text-indigo-700">
                {{ $ultimoPadrao }}
            </p>
        </div>
        <div class="text-right">
            <p class="text-sm text-slate-600">Olá, <span class="font-semibold">{{ $username }}</span></p>
            <form action="{{ route('logout') }}" method="POST" class="inline">
                @csrf
                <button class="text-xs text-red-500 hover:underline">sair</button>
            </form>
        </div>
    </div>

    {{-- ============== SEÇÃO 2: Histórico (tempo real via SSE) ============== --}}
    <div class="bg-white rounded-xl shadow p-4">
        <div class="flex items-center justify-between mb-3">
            <h2 class="font-semibold">Mensagens</h2>
            <span class="text-xs text-slate-400" x-text="conectado ? 'conectado (SSE)' : 'desconectado'"></span>
        </div>

        <div class="h-80 overflow-y-auto space-y-2 border border-slate-100 rounded-lg p-3 bg-slate-50">
            {{--
                Renderização das mensagens com Alpine (reativo).
                TODO: usar <template x-for="msg in mensagens" :key="..."> para listar.
                      Dentro, mostrar msg.username e msg.content.
            --}}
            <template x-if="mensagens.length === 0">
                <p class="text-sm text-slate-400 text-center mt-10">Nenhuma mensagem ainda.</p>
            </template>

            <template x-for="(msg, i) in mensagens" :key="i">
                <div class="text-sm">
                    <span class="font-semibold text-indigo-700" x-text="msg.username"></span>
                    <span class="text-slate-400">:</span>
                    <span x-text="msg.content"></span>
                </div>
            </template>

        </div>
    </div>

    {{-- ============== SEÇÃO 3: Enviar mensagem (Bidirecional curto) ============== --}}
    <div class="bg-white rounded-xl shadow p-4">
        {{--
            Envio via Alpine (fetch). O form chama enviar() em vez de dar submit
            tradicional, para não recarregar a página.
            TODO: implementar enviar() no script (fetch POST /chat/enviar).
        --}}
        <form @submit.prevent="enviar()" class="flex gap-2">
            <input
                type="text"
                x-model="novaMensagem"
                placeholder="Digite sua mensagem..."
                class="flex-1 rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-400"
            >
            <button
                type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 rounded-lg transition"
            >
                Enviar
            </button>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function chat() {
    return {
        mensagens: [],
        novaMensagem: '',
        conectado: false,

        iniciar() {
            // Server Streaming (gRPC) chega ao browser via SSE.
            const es = new EventSource('/stream');
            es.onopen = () => { this.conectado = true; };
            es.onmessage = (e) => {
                this.mensagens.push(JSON.parse(e.data));
            };
            es.onerror = () => { this.conectado = false; };
        },

        async enviar() {
            const texto = this.novaMensagem.trim();
            if (texto === '') return;

            const token = document.querySelector('meta[name="csrf-token"]').content;

            await fetch('/chat/enviar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({ content: texto }),
            });

            // A mensagem volta pela SSE; aqui só limpamos o input.
            this.novaMensagem = '';
        },
    };
}
</script>
@endsection
