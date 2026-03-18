// frontend/src/ErrorBoundary.jsx
// Capture les erreurs React et les envoie à Elasticsearch

import React from 'react';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { hasError: false };
  }

  static getDerivedStateFromError() {
    return { hasError: true };
  }

  componentDidCatch(error, errorInfo) {
    const logEntry = {
      timestamp: new Date().toISOString(),
      app: 'react',
      level: 'error',
      message: error.message,
      stack: error.stack,
      componentStack: errorInfo.componentStack,
      url: window.location.href,
      userAgent: navigator.userAgent,
      userId: localStorage.getItem('userId') || 'anonymous',
    };

    // Envoyer l'erreur à Elasticsearch
    fetch(`${import.meta.env.VITE_ELASTICSEARCH_URL || 'http://localhost:9200'}/react-logs/_doc`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(logEntry),
    }).catch((err) => {
      console.error('Impossible d\'envoyer le log à Elasticsearch:', err);
    });

    // Garder aussi dans la console
    console.error('ErrorBoundary caught:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div style={{ padding: '2rem', textAlign: 'center' }}>
          <h2>Une erreur est survenue.</h2>
          <p>Notre équipe a été notifiée automatiquement.</p>
          <button onClick={() => this.setState({ hasError: false })}>
            Réessayer
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;