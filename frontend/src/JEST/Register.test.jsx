import React from "react";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import Register from "../pages/Register";

// ─────────────────────────────────────
// MOCKS
// ─────────────────────────────────────

const mockNavigate = jest.fn();

jest.mock("react-router-dom", () => ({
  ...jest.requireActual("react-router-dom"),
  useNavigate: () => mockNavigate,
}));

jest.mock("../images/logo.png", () => "logo.png");

jest.mock("../style/register.module.css", () => {
  return new Proxy({}, { get: (_, key) => key });
});

// fetch global mock
global.fetch = jest.fn();

// ─────────────────────────────────────
// HELPERS
// ─────────────────────────────────────

const renderRegister = () =>
  render(
    <MemoryRouter>
      <Register />
    </MemoryRouter>,
  );

// ─────────────────────────────────────
// CLEANUP GLOBAL
// ─────────────────────────────────────

beforeEach(() => {
  jest.clearAllMocks();
  fetch.mockReset();
});

// ─────────────────────────────────────
// STEP NAV HELPERS
// ─────────────────────────────────────

const goStep2 = () => {
  renderRegister();

  fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
    target: { value: "Jean" },
  });

  fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
    target: { value: "Dupont" },
  });

  fireEvent.click(screen.getByText("Continuer"));
};

const goStep3 = () => {
  goStep2();

  fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
    target: { value: "jean@gmail.com" },
  });

  fireEvent.click(screen.getByText("Continuer"));
};

// ─────────────────────────────────────
// STEP 1
// ─────────────────────────────────────

describe("Register - Step 1", () => {
  test("affiche formulaire", () => {
    renderRegister();

    expect(screen.getByText("Qui êtes-vous ?")).toBeInTheDocument();
    expect(screen.getByText("Rejoindre open-hub")).toBeInTheDocument();
  });

  test("prénom vide -> erreur", () => {
    renderRegister();

    fireEvent.click(screen.getByText("Continuer"));

    expect(screen.getAllByText("Champ requis").length).toBeGreaterThan(0);
  });
});

// ─────────────────────────────────────
// STEP 2
// ─────────────────────────────────────

describe("Register - Step 2", () => {
  test("email invalide", () => {
    goStep2();

    fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
      target: { value: "invalidemail" },
    });

    fireEvent.click(screen.getByText("Continuer"));

    expect(screen.getByText("L'email doit contenir un @")).toBeInTheDocument();
  });

  test("passage step 3", () => {
    goStep2();

    fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
      target: { value: "test@gmail.com" },
    });

    fireEvent.click(screen.getByText("Continuer"));

    expect(screen.getByText("Vos disponibilités")).toBeInTheDocument();
  });
});

// ─────────────────────────────────────
// STEP 3
// ─────────────────────────────────────

describe("Register - Step 3", () => {
  test("password invalide bloque submit", async () => {
    renderRegister();

    fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
      target: { value: "Jean" },
    });

    fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
      target: { value: "Dupont" },
    });

    fireEvent.click(screen.getByText("Continuer"));

    fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
      target: { value: "jean@gmail.com" },
    });

    fireEvent.change(screen.getByPlaceholderText("6 caractères minimum"), {
      target: { value: "abc" },
    });

    fireEvent.click(screen.getByText("Continuer"));
    fireEvent.click(screen.getByText("Créer mon compte"));

    await waitFor(() => {
      expect(screen.getByText("Mot de passe invalide")).toBeInTheDocument();
    });

    expect(fetch).not.toHaveBeenCalled();
  });
});
