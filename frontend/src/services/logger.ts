/// <reference types="vite/client" />

type LogLevel = 'debug' | 'info' | 'warn' | 'error';

interface LogPayload {
  level: LogLevel;
  message: string;
  context?: Record<string, unknown>;
  timestamp: string;
  url: string;
  userAgent: string;
}

const API_URL = import.meta.env.VITE_API_URL ?? '';

const buffer: LogPayload[] = [];
let flushTimer: ReturnType<typeof setTimeout> | null = null;

const flush = async () => {
  if (buffer.length === 0) return;
  const logs = buffer.splice(0, buffer.length);
  try {
    await fetch(`${API_URL}/api/logs`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(logs.length === 1 ? logs[0] : logs),
    });
  } catch {
    console.warn('[Logger] Failed to send logs');
  }
};

const log = (level: LogLevel, message: string, context?: Record<string, unknown>) => {
  const payload: LogPayload = {
    level, message, context,
    timestamp: new Date().toISOString(),
    url: window.location.href,
    userAgent: navigator.userAgent,
  };

  console[level](`[${level.toUpperCase()}] ${message}`, context ?? '');

  if (import.meta.env.DEV) return;

  buffer.push(payload);
  if (flushTimer) clearTimeout(flushTimer);
  flushTimer = setTimeout(flush, 2000);
};

export const logger = {
  debug: (msg: string, ctx?: Record<string, unknown>) => log('debug', msg, ctx),
  info:  (msg: string, ctx?: Record<string, unknown>) => log('info',  msg, ctx),
  warn:  (msg: string, ctx?: Record<string, unknown>) => log('warn',  msg, ctx),
  error: (msg: string, ctx?: Record<string, unknown>) => log('error', msg, ctx),
};

window.addEventListener('error', (event) => {
  logger.error('Unhandled JS error', {
    message:  event.message,
    filename: event.filename,
    lineno:   event.lineno,
  });
});

window.addEventListener('unhandledrejection', (event) => {
  logger.error('Unhandled Promise rejection', {
    reason: String(event.reason),
  });
});