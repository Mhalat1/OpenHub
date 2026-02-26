import { onLCP, onFID, onCLS, onFCP } from 'web-vitals';

const API = import.meta.env.VITE_API_URL;

let sessionStart = Date.now();
let pageViews = 0;
let jsErrors = 0;

// Compte les JS errors
window.addEventListener('error', () => {
  jsErrors++;
  sendMetrics();
});

// Compte les page views
pageViews++;

function sendMetrics(extra = {}) {
  fetch(`${API}/metrics/frontend/collect`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      page_load_time: performance.now() / 1000,
      page_views: pageViews,
      js_errors: jsErrors,
      bounce: Date.now() - sessionStart < 10000 ? 1 : 0,
      ...extra
    })
  }).catch(() => {});
}

onLCP(m => sendMetrics({ lcp: m.value / 1000 }));
onFID(m => sendMetrics({ fid: m.value / 1000 }));
onCLS(m => sendMetrics({ cls: m.value }));

// Envoie au chargement
window.addEventListener('load', () => sendMetrics());