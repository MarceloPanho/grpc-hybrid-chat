<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'grpc-hybrid-chat')</title>

    {{-- Tailwind CSS via CDN (exceção: visual não é o foco do laboratório) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Alpine.js via CDN (reatividade no front sem build) --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <header class="bg-slate-900 text-white px-6 py-4 shadow">
            <div class="max-w-4xl mx-auto flex items-center justify-between">
                <h1 class="text-lg font-semibold tracking-tight">
                    grpc-hybrid-chat
                </h1>
                <span class="text-xs text-slate-400">gRPC · Laravel · Node.js</span>
            </div>
        </header>

        <main class="flex-1 max-w-4xl w-full mx-auto px-6 py-8">
            @yield('conteudo')
        </main>

        <footer class="text-center text-xs text-slate-400 py-4">
            Laboratório de Serviços Web — demonstração dos padrões gRPC
        </footer>
    </div>

    @yield('scripts')
</body>
</html>
