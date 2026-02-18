import React from "react";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import "@testing-library/jest-dom";
import DonatePage from "../pages/DonatePage";

// Mock de fetch
global.fetch = jest.fn();

// Mock de window.location
delete window.location;
window.location = { href: "" };

// Mock de import.meta.env
jest.mock("../pages/DonatePage", () => {
  const actual = jest.requireActual("../pages/DonatePage");
  return {
    __esModule: true,
    default: actual.default,
  };
});

describe("DonatePage", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    window.location.href = "";
  });

  test("affiche correctement le titre et la description", () => {
    render(<DonatePage />);

    expect(screen.getByText(/Soutenir le projet OpenHub/i)).toBeInTheDocument();
    expect(
      screen.getByText(/OpenHub est un projet open source/i),
    ).toBeInTheDocument();
  });

  test("initialise le montant à 5€ par défaut", () => {
    render(<DonatePage />);

    // Utiliser getByRole au lieu de getByLabelText car le label n'est pas correctement associé
    const input = screen.getByRole("spinbutton");
    expect(input).toHaveValue(5);
  });

  test("permet de modifier le montant du don", () => {
    render(<DonatePage />);

    const input = screen.getByRole("spinbutton");
    fireEvent.change(input, { target: { value: "25" } });

    expect(input).toHaveValue(25);
  });

  test("affiche le bouton 'Faire un don' par défaut", () => {
    render(<DonatePage />);

    const button = screen.getByRole("button", { name: /Faire un don/i });
    expect(button).toBeInTheDocument();
    expect(button).not.toBeDisabled();
  });

  test("affiche 'Redirection...' pendant le chargement", async () => {
    fetch.mockImplementationOnce(
      () => new Promise((resolve) => setTimeout(resolve, 100)),
    );

    render(<DonatePage />);

    const button = screen.getByRole("button", { name: /Faire un don/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(screen.getByText(/Redirection.../i)).toBeInTheDocument();
    });
    expect(button).toBeDisabled();
  });

  test("affiche un message d'erreur si la requête échoue", async () => {
    fetch.mockResolvedValueOnce({
      ok: false,
      status: 500,
    });

    render(<DonatePage />);

    const button = screen.getByRole("button", { name: /Faire un don/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(
        screen.getByText(
          /Erreur lors de la création de la session de paiement/i,
        ),
      ).toBeInTheDocument();
    });
  });

  test("réactive le bouton après une erreur", async () => {
    fetch.mockResolvedValueOnce({
      ok: false,
      status: 500,
    });

    render(<DonatePage />);

    const button = screen.getByRole("button", { name: /Faire un don/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(button).not.toBeDisabled();
      expect(screen.getByText(/Faire un don/i)).toBeInTheDocument();
    });
  });

  test("efface l'erreur précédente lors d'une nouvelle tentative", async () => {
    // Premier appel - échec
    fetch.mockResolvedValueOnce({
      ok: false,
      status: 500,
    });

    render(<DonatePage />);

    const button = screen.getByRole("button", { name: /Faire un don/i });
    fireEvent.click(button);

    await waitFor(() => {
      expect(screen.getByText(/Erreur/i)).toBeInTheDocument();
    });

    // Deuxième appel - succès
    fetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({ url: "https://stripe.com/checkout" }),
    });

    fireEvent.click(button);

    await waitFor(() => {
      expect(screen.queryByText(/Erreur/i)).not.toBeInTheDocument();
    });
  });
});
