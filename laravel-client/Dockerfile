# ============================================================================
#  Dockerfile do cliente Laravel (borda pública).
#  Inclui PHP-FPM, a extensão gRPC, o protoc + plugin PHP e gera as classes
#  PHP do contrato em Generated/Chat/ durante o build.
# ============================================================================
FROM php:8.3-fpm

# ----------------------------------------------------------------------------
#  Dependências de sistema + ferramentas de build.
# ----------------------------------------------------------------------------
RUN apt-get update && apt-get install -y \
    git unzip zip curl \
    protobuf-compiler \
    protobuf-compiler-grpc \
    $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# ----------------------------------------------------------------------------
#  Extensão gRPC do PHP (necessária para falar gRPC a partir do Laravel).
#  Compilada via PECL — por isso os $PHPIZE_DEPS acima.
# ----------------------------------------------------------------------------
RUN pecl install grpc \
    && docker-php-ext-enable grpc

# Composer (gerenciador de dependências PHP).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ----------------------------------------------------------------------------
#  Plugin protoc para PHP (grpc_php_plugin).
#  Fornecido pelo pacote apt `protobuf-compiler-grpc` (instalado acima) em
#  /usr/bin/grpc_php_plugin. É ele que permite ao protoc gerar os stubs de
#  cliente (UserServiceClient/ChatServiceClient), além das mensagens.
# ----------------------------------------------------------------------------

# ----------------------------------------------------------------------------
#  Dependências PHP do projeto (grpc/grpc e google/protobuf via composer).
# ----------------------------------------------------------------------------
COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-interaction --prefer-dist || true

# Copia o restante da aplicação.
COPY . .

# ----------------------------------------------------------------------------
#  Geração das classes PHP a partir do contrato .proto.
#  O .proto é montado em /var/www/proto pelo docker-compose.
#  Saída: Generated/Chat/ com as mensagens e os stubs de cliente.
# ----------------------------------------------------------------------------
RUN mkdir -p Generated \
    && protoc \
        --proto_path=/var/www/proto \
        --php_out=Generated \
        --grpc_out=Generated \
        --plugin=protoc-gen-grpc=$(which grpc_php_plugin) \
        /var/www/proto/chat.proto \
    || echo "AVISO: geração protoc será refeita em runtime (ver entrypoint)."

# Permissões para o Laravel escrever em storage/cache.
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# ----------------------------------------------------------------------------
#  Entrypoint: regenera Generated/ no volume montado (o bind mount esconde o
#  Generated/ do build) e ajusta permissões de storage/ em runtime.
# ----------------------------------------------------------------------------
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
