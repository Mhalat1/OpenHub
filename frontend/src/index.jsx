import React from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter } from 'react-router-dom'; // ✅ ajouter
import './index.css';
import App from './App';
import reportWebVitals from './reportWebVitals';

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
  <React.StrictMode>
    <BrowserRouter> {/* envelopper App dans BrowserRouter */}
      <App />
    </BrowserRouter>
    <React.StrictMode>{/*Ça mesure le temps de chargement réel navigateur et envoie vers Symfony pour mesure prometheus*/}
      <App />
    </React.StrictMode>
  </React.StrictMode>
);

reportWebVitals();
