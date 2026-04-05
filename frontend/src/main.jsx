// Dans reportWebVitals.js — ajoute ça à la fin
const reportWebVitals = () => {
  // Envoi immédiat au chargement
  sendToBackend({
    page_views: 1,
    js_errors: jsErrors,
    bounce: 0,
    page_load_time: performance.now() / 1000,
  });

  import("web-vitals").then(({ getCLS, getFID, getFCP, getLCP, getTTFB }) => {
    getLCP((m) => sendToBackend({ lcp: m.value / 1000 }));
    getFID((m) => sendToBackend({ fid: m.value / 1000 }));
    getCLS((m) => sendToBackend({ cls: m.value }));
    getFCP((m) => sendToBackend({ page_load_time: m.value / 1000 }));
    getTTFB(() =>
      sendToBackend({
        js_errors: jsErrors,
        bounce: Date.now() - sessionStart < 10000 ? 1 : 0,
      }),
    );
  });
};