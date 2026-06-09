# Dockerfile do microsserviço gRPC em Node.js
# Imagem enxuta baseada em Alpine para reduzir o tamanho final.
FROM node:20-alpine

# Diretório de trabalho dentro do container.
WORKDIR /app

# Copiamos primeiro só os manifestos de dependências.
# Isso aproveita o cache de camadas do Docker: enquanto package.json
# não mudar, o `npm install` não roda de novo a cada build.
COPY package*.json ./

# Instala apenas as dependências de produção.
RUN npm install --omit=dev

# Copia o código do servidor (server.js, test-client.js).
COPY . .

# O contrato .proto vive fora desta pasta (em /proto na raiz do projeto).
# Ele é montado/copiado via docker-compose, então não o copiamos aqui.

# Porta do servidor gRPC.
EXPOSE 50051

# Comando padrão: sobe o servidor.
CMD ["node", "server.js"]
