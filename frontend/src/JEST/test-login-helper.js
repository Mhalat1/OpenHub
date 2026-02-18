// src/JEST/test-login-helper.js - CORRIGÉ
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import Login from "../pages/Login";

// Fonction qui exécute un vrai login dans les tests
export const performLogin = async (
  email = "user@user",
  password = "useruser",
) => {
  console.log(`🔐 Début du login automatique pour: ${email}`);

  // Crée un token mock
  const mockToken = `jwt-token-${Date.now()}`;

  // Mock fetch pour le login - CORRECTION ICI
  global.fetch.mockResolvedValueOnce({
    ok: true,
    json: async () => ({
      token: mockToken,
    }),
  });

  // Rendu du composant Login
  render(
    <MemoryRouter>
      <Login />
    </MemoryRouter>,
  );

  // Remplissage automatique
  const emailInput = screen.getByPlaceholderText("votre@email.com");
  const passwordInput = screen.getByPlaceholderText("Votre mot de passe");
  const submitButton = screen.getByRole("button", { name: /Se connecter/i });

  fireEvent.change(emailInput, { target: { value: email } });
  fireEvent.change(passwordInput, { target: { value: password } });
  fireEvent.click(submitButton);

  // Attend la réponse
  await waitFor(
    () => {
      expect(global.fetch).toHaveBeenCalled();
    },
    { timeout: 3000 },
  );

  console.log(`✅ Login automatique réussi pour: ${email}`);
  console.log(`📦 Token généré: ${mockToken}`);

  return {
    token: mockToken,
    email: email,
  };
};

// Helper pour nettoyer
export const cleanupLogin = () => {
  if (window.localStorage && window.localStorage.clear) {
    window.localStorage.clear();
  }
  jest.clearAllMocks();
};
