#!/bin/sh
# ============================================================================
#  Entrypoint do cliente Laravel.
#
#  O docker-compose monta ./laravel-client em /var/www/html, o que ESCONDE o
#  Generated/ criado durante o build da imagem. Por isso regeneramos as classes
#  do contrato .proto aqui, em runtime, gravando direto no volume montado.
#  Também garantimos que o Laravel consiga escrever em storage/ e bootstrap/cache
#  (o bind mount traz as permissões do host, onde o www-data não é dono).
# ============================================================================
set -e

PROTO=/var/www/proto/chat.proto
GEN=/var/www/html/Generated

if [ -f "$PROTO" ]; then
    echo "[entrypoint] Gerando classes gRPC em Generated/ ..."
    mkdir -p "$GEN"
    if protoc \
            --proto_path=/var/www/proto \
            --php_out="$GEN" \
            --grpc_out="$GEN" \
            --plugin=protoc-gen-grpc="$(which grpc_php_plugin)" \
            "$PROTO"; then
        echo "[entrypoint] Classes geradas com sucesso."
    else
        echo "[entrypoint] AVISO: falha ao gerar as classes gRPC do contrato."
    fi
else
    echo "[entrypoint] AVISO: $PROTO não encontrado; pulei a geração."
fi

# Permite ao php-fpm (www-data) escrever sessões/views/cache no volume montado.
chmod -R 777 storage bootstrap/cache 2>/dev/null || true

exec "$@"
