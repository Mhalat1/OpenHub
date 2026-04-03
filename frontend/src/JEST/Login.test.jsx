import "@testing-library/jest-dom";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { BrowserRouter } from "react-router-dom";
import Login from "../pages/Login";

const mockNavigate = jest.fn();

jest.mock("react-router-dom", () => ({
  ...jest.requireActual("react-router-dom"),
  useNavigate: () => mockNavigate,
}));

const setup = () => render(<BrowserRouter><Login /></BrowserRouter>);
const fillAndSubmit = (email, password) => {
  if (email) fireEvent.change(screen.getByLabelText("Adresse email"), { target: { value: email } });
  if (password) fireEvent.change(screen.getByLabelText("Mot de passe"), { target: { value: password } });
  fireEvent.click(screen.getByRole("button", { name: /se connecter/i }));
};

beforeEach(() => { jest.clearAllMocks(); localStorage.clear(); global.fetch = jest.fn(); });
afterEach(() => jest.restoreAllMocks());

describe("Login Component", () => {
  describe("Rendering", () => {
    it("affiche les éléments principaux", () => {
      setup();
      expect(screen.getByText("Bienvenue sur open-hub")).toBeInTheDocument();
      expect(screen.getByLabelText("Adresse email")).toBeInTheDocument();
      expect(screen.getByLabelText("Mot de passe")).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /se connecter/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /créer un compte/i })).toBeInTheDocument();
      expect(screen.getByRole("button", { name: /mot de passe oublié/i })).toBeInTheDocument();
    });
  });

  describe("Validation", () => {
    it.each([
      ["", "", "Veuillez remplir tous les champs"],
      ["test@example.com", "", "Veuillez remplir tous les champs"],
      ["", "password123", "Veuillez remplir tous les champs"],
      ["invalidemail", "password123", "Invalid email format"],
    ])('email="%s" password="%s" → "%s"', async (email, password, error) => {
      setup();
      fillAndSubmit(email, password);
      await waitFor(() => expect(screen.getByText(error)).toBeInTheDocument());
      expect(global.fetch).not.toHaveBeenCalled();
    });
  });

  describe("Soumission", () => {
    it("connexion réussie → navigate vers /", async () => {
      global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({ token: "fake-token" }) });
      setup();
      fillAndSubmit("test@example.com", "password123");
      await waitFor(() => expect(mockNavigate).toHaveBeenCalled());
      expect(localStorage.getItem("token")).toBe("fake-token");
    });

    it("identifiants incorrects → affiche l'erreur", async () => {
      global.fetch.mockResolvedValueOnce({ ok: false, json: async () => ({ message: "Identifiants incorrects" }) });
      setup();
      fillAndSubmit("test@example.com", "wrongpassword");
      await waitFor(() => expect(screen.getByText("Identifiants incorrects")).toBeInTheDocument());
      expect(mockNavigate).not.toHaveBeenCalled();
    });

    it("token manquant → affiche l'erreur", async () => {
      global.fetch.mockResolvedValueOnce({ ok: true, json: async () => ({}) });
      setup();
      fillAndSubmit("test@example.com", "password123");
      await waitFor(() => expect(screen.getByText("Token manquant dans la réponse")).toBeInTheDocument());
    });

    it("erreur réseau → affiche l'erreur", async () => {
      global.fetch.mockRejectedValueOnce(new Error("Network error"));
      setup();
      fillAndSubmit("test@example.com", "password123");
      await waitFor(() => expect(screen.getByText("Network error")).toBeInTheDocument());
    });
  });

  describe("Navigation", () => {
    it.each([
      [/créer un compte/i, "/register"],
      [/mot de passe oublié/i, "/reset-password"],
    ])('bouton "%s" → "%s"', (name, path) => {
      setup();
      fireEvent.click(screen.getByRole("button", { name }));
      expect(mockNavigate).toHaveBeenCalledWith(path);
    });
  });
});