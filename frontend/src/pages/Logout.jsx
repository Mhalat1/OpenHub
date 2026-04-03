import "../style/logout.css";
import React, { useState } from "react";
const LogoutButton = () => {
  const handleLogout = () => {
    // 🔐 Supprime le token JWT
    localStorage.removeItem("token");

    // 🔄 Redirige automatiquement vers la page de login après 2 secondes
    setTimeout(() => {
      window.location.href = "/login";
    });
  };

  const handleReturnHome = () => {
    // 🏠 Redirige vers la page d'accueil
    window.location.href = "/home";
  };

  return (
    <div className="logout-container">
      <div className="logout-card">
        <h1 className="logout-title">See you soon 👋</h1>
        <p className="logout-subtitle">
          You’ve been successfully logged out of your account.
        </p>
        <p className="logout-text">
          You’ll be redirected to the login page shortly. If you prefer, you can
          return to the home page instead.
        </p>

        <div className="logout-actions">
          <button onClick={handleLogout} className="logout-btn">
            Logout
          </button>
          <button onClick={handleReturnHome} className="return-btn">
            Return to Home
          </button>
        </div>
      </div>
    </div>
  );
};

export default LogoutButton;
