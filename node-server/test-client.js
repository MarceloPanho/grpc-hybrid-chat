// ============================================================================
//  test-client.js — Demonstração dos três padrões gRPC pelo terminal
// ============================================================================
//  Roteiro:
//    1) testarUnary()           → Login (1 requisição, 1 resposta)
//    2) testarServerStreaming() → GetHistory (1 requisição, N respostas)
//    3) demonstrar()            → Chat Bidirecional com 2 clientes paralelos
//
//  O Bidirecional só faz sentido com clientes que MANTÊM o stream aberto.
//  Por isso ele é demonstrado aqui (Node.js), e não no Laravel (stateless).
// ============================================================================

const path = require('path');
const grpc = require('@grpc/grpc-js');
const protoLoader = require('@grpc/proto-loader');

// ----------------------------------------------------------------------------
//  Cores ANSI para diferenciar cada cliente no terminal.
// ----------------------------------------------------------------------------
const cores = {
  reset: '\x1b[0m',
  ciano: '\x1b[36m',   // cliente A
  amarelo: '\x1b[33m', // cliente B
  cinza: '\x1b[90m',   // sistema/logs
  verde: '\x1b[32m',
};

// Endereço do servidor gRPC. Em Docker, troque por "node-server:50051".
const ENDERECO = process.env.GRPC_ADDR || 'localhost:50051';

// ----------------------------------------------------------------------------
//  Carregamento do contrato (igual ao server.js).
// ----------------------------------------------------------------------------
const PROTO_PATH = path.join(__dirname, '..', 'proto', 'chat.proto');
const packageDefinition = protoLoader.loadSync(PROTO_PATH, {
  keepCase: true,
  longs: String,
  enums: String,
  defaults: true,
  oneofs: true,
});
const proto = grpc.loadPackageDefinition(packageDefinition).chat;

// Helper: pausa por `ms` milissegundos (útil para encadear envios no roteiro).
function dormir(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

// ----------------------------------------------------------------------------
//  1) UNARY — Login
// ----------------------------------------------------------------------------
function testarUnary() {
  return new Promise((resolve, reject) => {
    const cliente = new proto.UserService(
      ENDERECO,
      grpc.credentials.createInsecure()
    );

    cliente.Login({ username: 'marcelo' }, (err, res) => {
      if (err) {
        console.error('Erro no Login:', err.message);
        return reject(err);
      }

      console.log('Login bem-sucedido!');
      console.log('user_id:', res.user_id);
      console.log('token:', res.token);

      resolve(res);
    });
  });
}

// ----------------------------------------------------------------------------
//  2) SERVER STREAMING — GetHistory
// ----------------------------------------------------------------------------
function testarServerStreaming() {
  return new Promise((resolve, reject) => {
    const cliente = new proto.ChatService(
      ENDERECO,
      grpc.credentials.createInsecure()
    );

    const stream = cliente.GetHistory({})

    stream.on('data', (msg) => {
      console.log('Mensagem recebida:', msg);
    });

    stream.on('end', (msg) => {
      console.log('Histórico recebido com sucesso!');
      resolve();
    });

    stream.on('error', (err) => {
      console.error('Erro no streaming:', err.message);
      reject(err);
    });
  });
}

// ----------------------------------------------------------------------------
//  3) BIDIRECIONAL — Chat com dois clientes paralelos
// ----------------------------------------------------------------------------

// Cria um "cliente de chat" identificado por nome/cor que abre o stream Chat,
// escuta mensagens recebidas e expõe uma forma de enviar.
function criarClienteChat(nome, cor) {
  const cliente = new proto.ChatService(
    ENDERECO,
    grpc.credentials.createInsecure()
  );

  const stream = cliente.Chat();

  stream.on('data', (msg) => {
    if (msg.username === nome) {
      // Eco da própria mensagem — log discreto
      console.log(`${cor}[${nome}] eco próprio: "${msg.content}"\x1b[0m`);
    } else {
      // Mensagem de outro usuário
      console.log(`${cor}[${nome}] recebeu de ${msg.username}: ${msg.content}\x1b[0m`);
    }
  });

  stream.on('end', () => {
    console.log(`${cor}[${nome}] stream encerrado pelo servidor.\x1b[0m`);
  });

  stream.on('error', (err) => {
    console.error(`${cor}[${nome}] erro no stream: ${err.message}\x1b[0m`);
  });

  return {
    nome,
    cor,
    stream,
    enviar(content) {
      stream.write({
        user_id: `id-${nome}`,
        username: nome,
        content,
        timestamp: Date.now(),
      });
    },
  };
}

// Orquestra a conversa: A e B conectam juntos e trocam mensagens intercaladas.
async function demonstrar() {
  const A = criarClienteChat('Viviane', '\x1b[36m');   // ciano
  const B = criarClienteChat('Marcelo',   '\x1b[33m');   // amarelo

  // Roteiros em paralelo para simular conversa intercalada
  await Promise.all([
    (async () => {
      await dormir(200);
      A.enviar('Oi Marcelo, tudo bem?');

      await dormir(600);
      A.enviar('Que bom! Já testou o gRPC bidirecional?');

      await dormir(600);
      A.enviar('Exato! Streams bidirecionais são poderosos.');
    })(),

    // Roteiro de Marcelo
    (async () => {
      await dormir(400);
      B.enviar('Tudo ótimo, Viviane! E você?');

      await dormir(600);
      B.enviar('Sim! Estou impressionado com o Server Streaming.');

      await dormir(600);
      B.enviar('Com certeza. Valeu pela demo!');
    })(),
  ]);

  // Aguardar a última mensagem ser processada antes de encerrar
  await dormir(500);

  // Encerrar os streams de ambos os clientes
  A.stream.end();
  B.stream.end();

  console.log('\x1b[0mDemo encerrada — streams fechados.');
}

// ----------------------------------------------------------------------------
//  Orquestração principal: roda os três testes em sequência.
// ----------------------------------------------------------------------------
async function main() {
  console.log(`${cores.cinza}=== Demo gRPC: Unary → Server Streaming → Bidirecional ===${cores.reset}`);

  try {
    // Login: chamada Unary (uma requisição, uma resposta).
    console.log(`\n${cores.cinza}--- [1/3] Testando Login (Unary) ---${cores.reset}`);
    await testarUnary();

    // Histórico: Server Streaming (o servidor envia várias mensagens em sequência).
    console.log(`\n${cores.cinza}--- [2/3] Testando Histórico (Server Streaming) ---${cores.reset}`);
    await testarServerStreaming();

    // Chat: streaming bidirecional (cliente e servidor trocam mensagens ao vivo).
    console.log(`\n${cores.cinza}--- [3/3] Testando Chat (Bidirecional) ---${cores.reset}`);
    await demonstrar();

    console.log(`\n${cores.cinza}=== Demo concluída com sucesso! ===${cores.reset}`);

  } catch (err) {
    console.error(`${cores.reset}Erro fatal durante a demo:`, err.message);
    process.exit(1);                                // ← sair com código de erro se algo falhar
  }

  process.exit(0);                                  // ← encerrar o processo ao final
}

main();
