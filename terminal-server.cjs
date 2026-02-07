#!/usr/bin/env node
/**
 * VSISPanel Terminal WebSocket Server
 *
 * Provides web terminal access via xterm.js + node-pty.
 * Authentication via one-time tokens stored in Redis.
 */

const { WebSocketServer } = require('ws');
const pty = require('node-pty');
const http = require('http');
const { createClient } = require('redis');

const PORT = process.env.TERMINAL_PORT || 8022;
const IDLE_TIMEOUT = 30 * 60 * 1000; // 30 minutes
const REDIS_URL = process.env.REDIS_URL || 'redis://127.0.0.1:6379';
const REDIS_PREFIX = process.env.REDIS_PREFIX || 'vsispanel_database_';

let redis;

async function initRedis() {
  redis = createClient({ url: REDIS_URL });
  redis.on('error', (err) => console.error('Redis error:', err));
  await redis.connect();
  console.log('Connected to Redis');
}

// Simple HTTP health check server
const server = http.createServer((req, res) => {
  if (req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'ok', sessions: sessions.size }));
  } else {
    res.writeHead(404);
    res.end();
  }
});

const wss = new WebSocketServer({ server });
const sessions = new Map();

wss.on('connection', (ws, req) => {
  let authenticated = false;
  let ptyProcess = null;
  let sessionId = null;
  let idleTimer = null;

  const resetIdleTimer = () => {
    if (idleTimer) clearTimeout(idleTimer);
    idleTimer = setTimeout(() => {
      console.log(`Session ${sessionId} timed out due to inactivity`);
      ws.send(JSON.stringify({ type: 'error', message: 'Session timed out due to inactivity' }));
      ws.close();
    }, IDLE_TIMEOUT);
  };

  ws.on('message', async (data) => {
    const msg = data.toString();

    // First message must be authentication
    if (!authenticated) {
      try {
        const parsed = JSON.parse(msg);
        if (parsed.type === 'auth' && parsed.token) {
          const tokenData = await redis.get(`${REDIS_PREFIX}terminal:token:${parsed.token}`);
          if (!tokenData) {
            ws.send(JSON.stringify({ type: 'error', message: 'Invalid or expired token' }));
            ws.close();
            return;
          }

          const { userId, username } = JSON.parse(tokenData);
          await redis.del(`${REDIS_PREFIX}terminal:token:${parsed.token}`);

          sessionId = `term_${Date.now()}_${Math.random().toString(36).substr(2, 6)}`;
          authenticated = true;

          // Determine shell and user
          const shell = process.env.SHELL || '/bin/bash';
          const cols = parsed.cols || 80;
          const rows = parsed.rows || 24;

          // Spawn PTY process
          ptyProcess = pty.spawn(shell, [], {
            name: 'xterm-256color',
            cols,
            rows,
            cwd: username === 'root' ? '/root' : `/home/${username}`,
            env: {
              ...process.env,
              TERM: 'xterm-256color',
              HOME: username === 'root' ? '/root' : `/home/${username}`,
              USER: username,
              SHELL: shell,
            },
          });

          sessions.set(sessionId, {
            pty: ptyProcess,
            userId,
            username,
            createdAt: new Date().toISOString(),
          });

          // Track session in Redis
          await redis.hSet(`${REDIS_PREFIX}terminal:sessions`, sessionId, JSON.stringify({
            userId,
            username,
            createdAt: new Date().toISOString(),
          }));

          // Send auth success
          ws.send(JSON.stringify({ type: 'auth', success: true, sessionId }));

          // Stream PTY output to WebSocket
          ptyProcess.onData((output) => {
            if (ws.readyState === ws.OPEN) {
              ws.send(JSON.stringify({ type: 'output', data: output }));
            }
          });

          ptyProcess.onExit(({ exitCode }) => {
            ws.send(JSON.stringify({ type: 'exit', code: exitCode }));
            ws.close();
          });

          resetIdleTimer();
          console.log(`Session ${sessionId} created for user ${username} (${userId})`);
        }
      } catch (e) {
        ws.send(JSON.stringify({ type: 'error', message: 'Authentication failed' }));
        ws.close();
      }
      return;
    }

    // Handle authenticated messages
    resetIdleTimer();

    try {
      const parsed = JSON.parse(msg);
      switch (parsed.type) {
        case 'input':
          if (ptyProcess && parsed.data) {
            ptyProcess.write(parsed.data);
          }
          break;
        case 'resize':
          if (ptyProcess && parsed.cols && parsed.rows) {
            ptyProcess.resize(parsed.cols, parsed.rows);
          }
          break;
        case 'ping':
          ws.send(JSON.stringify({ type: 'pong' }));
          break;
      }
    } catch (e) {
      // Raw input fallback (plain text = terminal input)
      if (ptyProcess) {
        ptyProcess.write(msg);
      }
    }
  });

  ws.on('close', async () => {
    if (idleTimer) clearTimeout(idleTimer);
    if (ptyProcess) {
      ptyProcess.kill();
    }
    if (sessionId) {
      sessions.delete(sessionId);
      try {
        await redis.hDel(`${REDIS_PREFIX}terminal:sessions`, sessionId);
      } catch (e) { /* ignore */ }
      console.log(`Session ${sessionId} closed`);
    }
  });

  ws.on('error', (err) => {
    console.error(`WebSocket error for session ${sessionId}:`, err.message);
  });
});

async function start() {
  try {
    await initRedis();
    const BIND_HOST = process.env.TERMINAL_BIND_HOST || '0.0.0.0';
    server.listen(PORT, BIND_HOST, () => {
      console.log(`VSISPanel Terminal Server listening on ${BIND_HOST}:${PORT}`);
    });
  } catch (err) {
    console.error('Failed to start terminal server:', err);
    process.exit(1);
  }
}

// Graceful shutdown
process.on('SIGTERM', async () => {
  console.log('Shutting down terminal server...');
  for (const [id, session] of sessions) {
    session.pty.kill();
  }
  sessions.clear();
  if (redis) await redis.quit();
  server.close();
  process.exit(0);
});

process.on('SIGINT', () => process.emit('SIGTERM'));

start();
