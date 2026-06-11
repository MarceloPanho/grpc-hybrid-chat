// ============================================================================
//  server.js — Microsserviço gRPC do laboratório grpc-hybrid-chat
// ============================================================================
//  Carrega o contrato .proto em runtime, mantém o estado em memória e
//  expõe os três padrões gRPC: Login (Unary), GetHistory (Server Streaming)
//  e Chat (Bidirecional).
// ============================================================================

const path = require('path');
const crypto = require('crypto');
const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');

// ----------------------------------------------------------------------------
//  1) Carregamento do contrato .proto EM RUNTIME (sem protoc)
// ----------------------------------------------------------------------------
//  O .proto vive na raiz do projeto, em /proto/chat.proto. Em produção (Docker)
//  ele será montado em um caminho conhecido; localmente subimos um nível a
//  partir de node-server/. Ajuste PROTO_PATH se necessário no docker-compose.
const PROTO_PATH = path.join(__dirname, '..', 'proto', 'chat.proto');

// Opções de carregamento. Mantêm os nomes como estão no .proto (keepCase),
// usam Long para int64 e arrays/enums em formato amigável.
const packageDefinition = protoLoader.loadSync(PROTO_PATH, {
  keepCase: true,
  longs: String,
  enums: String,
  defaults: true,
  oneofs: true,
});

// Transforma a definição crua em objetos JS. Como o package é "chat",
// acessamos os serviços via proto.chat.UserService / proto.chat.ChatService.
const proto = grpc.loadPackageDefinition(packageDefinition).chat;

// ----------------------------------------------------------------------------
//  2) Estado em memória (substitui o banco de dados neste laboratório)
// ----------------------------------------------------------------------------

// user_id -> { user_id, username, token }
const usuarios = new Map();

// histórico de todas as mensagens já enviadas (objetos ChatMessage).
const historico = [];

// streams bidirecionais atualmente conectados, para fazer broadcast.
const streamsAtivos = new Set();

// ----------------------------------------------------------------------------
//  3) Handlers
// ----------------------------------------------------------------------------

// Log com horário e tag do padrão gRPC. Faz a janela de logs do servidor
// "reagir" a cada ação durante a demo pelo browser
function log(padrao, mensagem) {
  const hora = new Date().toLocaleTimeString('pt-BR');
  console.log(`[${hora}] [${padrao}] ${mensagem}`);
}

// --- Unary --- uma requisição, uma resposta.
function Login(call, callback) {
  const username = call.request.username;
  log('Unary', `Login recebido: username="${username}"`);

  if (!username || username.trim() === '') {
    log('Unary', 'Login rejeitado: username vazio');
    return callback({
      code: grpc.status.INVALID_ARGUMENT,
      message: 'Username não pode ser vazio',
    });
  }

  const user_id = crypto.randomUUID();
  const token = crypto.randomBytes(32).toString('hex');

  usuarios.set(token, { user_id, username });

  log('Unary', `Login OK: "${username}" → user_id=${user_id} (token emitido)`);
  callback(null, { user_id, username, token });
}

// --- Server Streaming --- uma requisição, várias respostas até call.end().
function GetHistory(call) {
  log('Server Streaming', `GetHistory iniciado: enviando ${historico.length} mensagem(ns) do histórico`);

  historico.forEach(msg => {
    call.write(msg);
  });

  call.end();
  log('Server Streaming', 'GetHistory finalizado: stream encerrado (call.end)');
}

// --- Bidirecional --- ambos os lados enviam e recebem pelo mesmo stream.
function Chat(call) {
  streamsAtivos.add(call);
  log('Bidirecional', `Novo cliente conectado ao Chat — streams ativos: ${streamsAtivos.size}`);

  // Mensagem recebida de um cliente: guarda no histórico e faz broadcast.
  call.on('data', (msg) => {
    log('Bidirecional', `Mensagem de "${msg.username}": "${msg.content}" — broadcast para ${streamsAtivos.size} stream(s)`);
    historico.push(msg);

    streamsAtivos.forEach(stream => {
      stream.write(msg);
    });
  });

  // Cliente fechou o stream: remove do broadcast e encerra o lado do servidor.
  call.on('end', () => {
    streamsAtivos.delete(call);
    call.end();
    log('Bidirecional', `Cliente desconectou — streams ativos: ${streamsAtivos.size}`);
  });

  // Stream rompido: apenas remove do broadcast.
  call.on('error', () => {
    streamsAtivos.delete(call);
    log('Bidirecional', `Stream rompido — streams ativos: ${streamsAtivos.size}`);
  });
}

// ----------------------------------------------------------------------------
//  4) Inicialização do servidor
// ----------------------------------------------------------------------------

function main() {
  const server = new grpc.Server();

  // Liga cada RPC do contrato à sua função handler.
  server.addService(proto.UserService.service, { Login });
  server.addService(proto.ChatService.service, { GetHistory, Chat });

  // Sem TLS neste laboratório (createInsecure). 0.0.0.0 para aceitar
  // conexões de fora do container.
  const endereco = '0.0.0.0:50051';
  server.bindAsync(endereco, grpc.ServerCredentials.createInsecure(), (erro, porta) => {
    if (erro) {
      console.error('Falha ao iniciar o servidor gRPC:', erro);
      process.exit(1);
    }
    console.log(`Servidor gRPC ouvindo em ${endereco} (porta ${porta})`);
  });
}

main();
