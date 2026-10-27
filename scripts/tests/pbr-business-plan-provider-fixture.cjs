// Disposable CI protocol double. It never calls a real AI provider.
const http = require('node:http');
const fs = require('node:fs');
const server = http.createServer(async (request, response) => {
  const json = (data, status = 200) => {
    response.writeHead(status, {'Content-Type': 'application/json'});
    response.end(JSON.stringify(data));
  };
  if (request.url === '/health') {
    return json({service: 'pbr-ai-advisor', knowledgeReady: true, vectors: 657, privateKnowledgeProtocol: 1, chatInterfaceProtocol: 1});
  }
  let body = '';
  for await (const chunk of request) body += chunk;
  const data = JSON.parse(body || '{}');
  if (request.url === '/internal/pbr/private/embed') {
    return json({vectors: data.texts.map(() => Array(128).fill(.25)), embeddingKey: 'synthetic-plan:128:private-v1'});
  }
  if (request.url === '/internal/pbr/private/extract') return json({text: 'PBR synthetic number 42', partial: false});
  if (request.url === '/internal/pbr/chat') {
    response.writeHead(200, {'Content-Type': 'text/event-stream'});
    response.end('data: {"type":"meta","mode":"rag"}\n\ndata: {"type":"delta","text":"The reviewed number is 42. [S1]"}\n\ndata: {"type":"done"}\n\n');
    return;
  }
  json({error: 'Unexpected synthetic endpoint'}, 404);
});
server.listen(0, '127.0.0.1', () => fs.writeFileSync(process.argv[2], String(server.address().port)));
process.on('SIGTERM', () => server.close(() => process.exit(0)));
