import "@testing-library/jest-dom";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import DonatePage from "../pages/DonatePage";

global.fetch = jest.fn();

beforeEach(() => jest.clearAllMocks());

const setup = () => render(<DonatePage />);
const getButton = () => screen.getByRole("button");
const getInput = () => screen.getByRole("spinbutton");

describe("DonatePage", () => {
  test("affiche le titre et la description", () => {
    setup();
    expect(screen.getByText(/Soutenir le projet open-hub/i)).toBeInTheDocument();
    expect(screen.getByText(/open-hub est un projet open source/i)).toBeInTheDocument();
  });

  test("montant par défaut à 5€", () => {
    setup();
    expect(getInput()).toHaveValue(5);
  });

  test("permet de modifier le montant", () => {
    setup();
    fireEvent.change(getInput(), { target: { value: "25" } });
    expect(getInput()).toHaveValue(25);
  });

  test("bouton actif par défaut", () => {
    setup();
    expect(screen.getByText(/Faire un don/i)).toBeInTheDocument();
    expect(getButton()).not.toBeDisabled();
  });

  test("affiche 'Redirection...' pendant le chargement", async () => {
    fetch.mockImplementationOnce(() => new Promise(() => {})); // ne résout jamais
    setup();
    fireEvent.click(getButton());
    await waitFor(() => expect(screen.getByText(/Redirection.../i)).toBeInTheDocument());
    expect(getButton()).toBeDisabled();
  });

  test("affiche une erreur si la requête échoue", async () => {
    fetch.mockResolvedValueOnce({ ok: false, status: 500 });
    setup();
    fireEvent.click(getButton());
    await waitFor(() =>
      expect(screen.getByText(/Erreur lors de la création de la session de paiement/i)).toBeInTheDocument()
    );
    expect(getButton()).not.toBeDisabled();
  });

  test("efface l'erreur lors d'une nouvelle tentative", async () => {
    fetch.mockResolvedValueOnce({ ok: false, status: 500 });
    setup();
    fireEvent.click(getButton());
    await waitFor(() => expect(screen.getByText(/Erreur/i)).toBeInTheDocument());

    fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ url: "https://stripe.com/checkout" }) });
    fireEvent.click(getButton());
    await waitFor(() => expect(screen.queryByText(/Erreur/i)).not.toBeInTheDocument());
  });
});