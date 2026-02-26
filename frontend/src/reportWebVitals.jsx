const API = import.meta.env.VITE_API_URL || 'https://openhub-backend.onrender.com';

let jsErrors = 0;
let sessionStart = Date.now();

window.addEventListener('error', () => { jsErrors++; });

function sendToBackend(data) {
  fetch(`${API}/metrics/frontend/collect`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  }).catch(() => {});
}

const reportWebVitals = () => {
  import('web-vitals').then(({ getCLS, getFID, getFCP, getLCP, getTTFB }) => {
    getLCP(m => sendToBackend({ lcp: m.value / 1000 }));
    getFID(m => sendToBackend({ fid: m.value / 1000 }));
    getCLS(m => sendToBackend({ cls: m.value }));
    getFCP(m => sendToBackend({ page_load_time: m.value / 1000 }));
    getTTFB(() => sendToBackend({
      page_views: 1,
      js_errors: jsErrors,
      bounce: Date.now() - sessionStart < 10000 ? 1 : 0
    }));
  });
};

export default reportWebVitals;