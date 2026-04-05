'use strict';


const http = require('http');

const PORT = parseInt(process.env.PORT || '3000', 10);


function handleRequest(req, res) {
  if (req.method === 'GET' && req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', service: 'notification-service' }));
    return;
  }

  if (req.method === 'POST' && req.url === '/notify') {
    let raw = '';

    req.on('data', chunk => { raw += chunk.toString(); });

    req.on('end', () => {
      let data;

      try {
        data = JSON.parse(raw);
      } catch {
        res.writeHead(400, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'Invalid JSON payload.' }));
        return;
      }

      const { affiliate_id, business_name, email } = data;

      if (!affiliate_id || !business_name || !email) {
        res.writeHead(422, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'affiliate_id, business_name, and email are required.' }));
        return;
      }


      console.log('─'.repeat(60));
      console.log('[NOTIFICATION] New Affiliate Registered');
      console.log(`  Time          : ${new Date().toISOString()}`);
      console.log(`  Affiliate ID  : ${affiliate_id}`);
      console.log(`  Business Name : ${business_name}`);
      console.log(`  Email         : ${email}`);
      console.log('  Action        : Welcome email dispatched (simulated)');
      console.log('─'.repeat(60));

      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ status: 'received', message: 'Notification logged.' }));
    });

    req.on('error', err => {
      console.error('[ERROR] Request stream error:', err.message);
      res.writeHead(500, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ error: 'Internal server error.' }));
    });

    return;
  }


  res.writeHead(404, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ error: `${req.method} ${req.url} not found.` }));
}


const server = http.createServer(handleRequest);

server.listen(PORT, () => {
  console.log(`[notification-service] listening on port ${PORT}`);
});


process.on('SIGTERM', () => {
  server.close(() => {
    console.log('[notification-service] shut down gracefully.');
    process.exit(0);
  });
});
