type LogLevel = 'debug' | 'info' | 'warn' | 'error';

interface LogPayload {
  level: LogLevel;
  message: string;
  context?: Record<string, unknown>;
  timestamp: string;
  url: string;
  userAgent: string;
}

const LOGSTASH_URL = 'http://localhost:8080';

// Buffer pour batcher les logs et éviter trop de requêtes
const buffer: LogPayload[] = [];
let flushTimer: ReturnType<typeof setTimeout> | null = null;

const flush = async () => {
  if (buffer.length === 0) return;
  const logs = buffer.splice(0, buffer.length);

  try {
    await fetch(LOGSTASH_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      // Envoi en batch
      body: JSON.stringify(logs.length === 1 ? logs[0] : logs),
    });
  } catch {
    // Silencieux : on ne veut pas que le logging crashe l'app
    console.warn('[Logger] Failed to send logs to Logstash');
  }
};

const log = (level: LogLevel, message: string, context?: Record<string, unknown>) => {
  const payload: LogPayload = {
    level,
    message,
    context,
    timestamp: new Date().toISOString(),
    url: window.location.href,
    userAgent: navigator.userAgent,
  };

  // Toujours logger en console aussi
  console[level](`[${level.toUpperCase()}] ${message}`, context ?? '');



  // Flush après 2s d'inactivité
  if (flushTimer) clearTimeout(flushTimer);
  flushTimer = setTimeout(flush, 2000);
};

export const logger = {
  debug: (msg: string, ctx?: Record<string, unknown>) => log('debug', msg, ctx),
  info:  (msg: string, ctx?: Record<string, unknown>) => log('info',  msg, ctx),
  warn:  (msg: string, ctx?: Record<string, unknown>) => log('warn',  msg, ctx),
  error: (msg: string, ctx?: Record<string, unknown>) => log('error', msg, ctx),
};

// Capture automatique des erreurs JS non gérées
window.addEventListener('error', (event) => {
  logger.error('Unhandled JS error', {
    message: event.message,
    filename: event.filename,
    lineno: event.lineno,
  });
});

// Capture des Promise rejetées
window.addEventListener('unhandledrejection', (event) => {
  logger.error('Unhandled Promise rejection', {
    reason: String(event.reason),
  });
});