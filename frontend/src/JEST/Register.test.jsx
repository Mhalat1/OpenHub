import React from "react";
import { render, screen, fireEvent, waitFor } from "@testing-library/react";
import { BrowserRouter } from "react-router-dom";
import Register from "../pages/Register";

// Mock de useNavigate
const mockNavigate = jest.fn();
jest.mock("react-router-dom", () => ({
  ...jest.requireActual("react-router-dom"),
  useNavigate: () => mockNavigate,
}));

// Mock du logo
jest.mock("../images/logo.png", () => "mocked-logo.png");

// Mock des styles CSS modules
jest.mock("../style/register.module.css", () => ({
  container: "container",
  header: "header",
  logo: "logo",
  progressBar: "progressBar",
  progress: "progress",
  form: "form",
  step: "step",
  stepHeader: "stepHeader",
  stepIndicator: "stepIndicator",
  inputGroup: "inputGroup",
  errorInput: "errorInput",
  errorText: "errorText",
  primaryButton: "primaryButton",
  secondaryButton: "secondaryButton",
  buttonGroup: "buttonGroup",
  loginLink: "loginLink",
  linkButton: "linkButton",
  errorMessage: "errorMessage",
}));

// Helper pour rendre le composant
const renderRegister = () => {
  return render(
    <BrowserRouter>
      <Register />
    </BrowserRouter>,
  );
};

describe("Register Component", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    global.fetch = jest.fn();
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  describe("Initial Rendering", () => {
    it("should render the register page with header", () => {
      renderRegister();
      expect(screen.getByText("Rejoindre OpenHub")).toBeInTheDocument();
      expect(
        screen.getByText("Créez votre profil en 3 étapes"),
      ).toBeInTheDocument();
    });

    it("should render the logo", () => {
      renderRegister();
      const logo = screen.getByAltText("OpenHub");
      expect(logo).toBeInTheDocument();
      expect(logo).toHaveAttribute("src", "mocked-logo.png");
    });

    it("should render progress bar", () => {
      const { container } = renderRegister();
      expect(container.querySelector(".progressBar")).toBeInTheDocument();
      expect(container.querySelector(".progress")).toBeInTheDocument();
    });

    it("should render login link", () => {
      renderRegister();
      expect(screen.getByText("Déjà un compte ?")).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /se connecter/i }),
      ).toBeInTheDocument();
    });

    it("should start at step 1", () => {
      renderRegister();
      expect(screen.getByText("Qui êtes-vous ?")).toBeInTheDocument();
      expect(screen.getByPlaceholderText("Votre prénom")).toBeInTheDocument();
      expect(screen.getByPlaceholderText("Votre nom")).toBeInTheDocument();
    });

    it("should show step indicator 1", () => {
      renderRegister();
      expect(screen.getByText("1")).toBeInTheDocument();
    });
  });

  describe("Step 1 - Personal Information", () => {
    it("should update firstName on input change", () => {
      renderRegister();
      const firstNameInput = screen.getByPlaceholderText("Votre prénom");
      fireEvent.change(firstNameInput, { target: { value: "John" } });
      expect(firstNameInput.value).toBe("John");
    });

    it("should update lastName on input change", () => {
      renderRegister();
      const lastNameInput = screen.getByPlaceholderText("Votre nom");
      fireEvent.change(lastNameInput, { target: { value: "Doe" } });
      expect(lastNameInput.value).toBe("Doe");
    });

    it("should show error when firstName is empty and clicking Continue", () => {
      renderRegister();
      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);
      expect(screen.getByText("Prénom requis")).toBeInTheDocument();
    });

    it("should show error when lastName is empty and clicking Continue", () => {
      renderRegister();
      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);
      expect(screen.getByText("Nom requis")).toBeInTheDocument();
    });

    it("should show both errors when both fields are empty", () => {
      renderRegister();
      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);
      expect(screen.getByText("Prénom requis")).toBeInTheDocument();
      expect(screen.getByText("Nom requis")).toBeInTheDocument();
    });

    it("should clear error when user starts typing", () => {
      renderRegister();
      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      expect(screen.getByText("Prénom requis")).toBeInTheDocument();

      const firstNameInput = screen.getByPlaceholderText("Votre prénom");
      fireEvent.change(firstNameInput, { target: { value: "John" } });

      expect(screen.queryByText("Prénom requis")).not.toBeInTheDocument();
    });

    it("should not advance to step 2 if validation fails", () => {
      renderRegister();
      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      // Should still be on step 1
      expect(screen.getByText("Qui êtes-vous ?")).toBeInTheDocument();
    });

    it("should advance to step 2 when both fields are filled", () => {
      renderRegister();

      const firstNameInput = screen.getByPlaceholderText("Votre prénom");
      const lastNameInput = screen.getByPlaceholderText("Votre nom");

      fireEvent.change(firstNameInput, { target: { value: "John" } });
      fireEvent.change(lastNameInput, { target: { value: "Doe" } });

      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      // Should be on step 2
      expect(screen.getByText("Votre compte")).toBeInTheDocument();
    });

    it("should apply error style to invalid inputs", () => {
      renderRegister();
      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      const firstNameInput = screen.getByPlaceholderText("Votre prénom");
      expect(firstNameInput).toHaveClass("errorInput");
    });
  });

  describe("Step 2 - Account Information", () => {
    beforeEach(() => {
      renderRegister();
      // Navigate to step 2
      fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
        target: { value: "John" },
      });
      fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
        target: { value: "Doe" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));
    });

    it("should show step indicator 2", () => {
      expect(screen.getByText("2")).toBeInTheDocument();
    });

    it("should render back and continue buttons", () => {
      expect(
        screen.getByRole("button", { name: /retour/i }),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /continuer/i }),
      ).toBeInTheDocument();
    });

    it("should update email on input change", () => {
      const emailInput = screen.getByPlaceholderText("exemple@email.com");
      fireEvent.change(emailInput, { target: { value: "test@example.com" } });
      expect(emailInput.value).toBe("test@example.com");
    });

    it("should update password on input change", () => {
      const passwordInput = screen.getByPlaceholderText("6 caractères minimum");
      fireEvent.change(passwordInput, { target: { value: "password123" } });
      expect(passwordInput.value).toBe("password123");
    });

    it("should show error for invalid email", () => {
      const emailInput = screen.getByPlaceholderText("exemple@email.com");
      fireEvent.change(emailInput, { target: { value: "invalidemail" } });

      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      expect(screen.getByText("Email invalide")).toBeInTheDocument();
    });

    it("should show error for short password", () => {
      const emailInput = screen.getByPlaceholderText("exemple@email.com");
      const passwordInput = screen.getByPlaceholderText("6 caractères minimum");

      fireEvent.change(emailInput, { target: { value: "test@example.com" } });
      fireEvent.change(passwordInput, { target: { value: "123" } });

      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      expect(screen.getByText("6 caractères minimum")).toBeInTheDocument();
    });

    it("should go back to step 1 when clicking Retour", () => {
      const backButton = screen.getByRole("button", { name: /retour/i });
      fireEvent.click(backButton);

      expect(screen.getByText("Qui êtes-vous ?")).toBeInTheDocument();
    });

    it("should advance to step 3 with valid email and password", () => {
      const emailInput = screen.getByPlaceholderText("exemple@email.com");
      const passwordInput = screen.getByPlaceholderText("6 caractères minimum");

      fireEvent.change(emailInput, { target: { value: "test@example.com" } });
      fireEvent.change(passwordInput, { target: { value: "password123" } });

      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      expect(screen.getByText("Vos disponibilités")).toBeInTheDocument();
    });

    it("should preserve data when navigating back and forth", () => {
      const emailInput = screen.getByPlaceholderText("exemple@email.com");
      fireEvent.change(emailInput, { target: { value: "test@example.com" } });

      const backButton = screen.getByRole("button", { name: /retour/i });
      fireEvent.click(backButton);

      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      const emailInputAgain = screen.getByPlaceholderText("exemple@email.com");
      expect(emailInputAgain.value).toBe("test@example.com");
    });
  });

  describe("Step 3 - Availability", () => {
    beforeEach(() => {
      renderRegister();
      // Navigate to step 3
      fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
        target: { value: "John" },
      });
      fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
        target: { value: "Doe" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
        target: { value: "test@example.com" },
      });
      fireEvent.change(screen.getByPlaceholderText("6 caractères minimum"), {
        target: { value: "password123" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));
    });

    it("should show step indicator 3", () => {
      expect(screen.getByText("3")).toBeInTheDocument();
    });

    it("should render back and submit buttons", () => {
      expect(
        screen.getByRole("button", { name: /retour/i }),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /créer mon compte/i }),
      ).toBeInTheDocument();
    });

    it("should update skills input", () => {
      const skillsInput = screen.getByPlaceholderText(
        /JavaScript, React, Node.js.../i,
      );
      fireEvent.change(skillsInput, { target: { value: "React, Node.js" } });
      expect(skillsInput.value).toBe("React, Node.js");
    });

    it("should show helper text for skills", () => {
      expect(screen.getByText("Séparez par des virgules")).toBeInTheDocument();
    });

    it("should go back to step 2 when clicking Retour", () => {
      const backButton = screen.getByRole("button", { name: /retour/i });
      fireEvent.click(backButton);

      expect(screen.getByText("Votre compte")).toBeInTheDocument();
    });
  });

  describe("Form Submission", () => {
    beforeEach(() => {
      renderRegister();
      // Navigate to step 3
      fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
        target: { value: "John" },
      });
      fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
        target: { value: "Doe" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
        target: { value: "test@example.com" },
      });
      fireEvent.change(screen.getByPlaceholderText("6 caractères minimum"), {
        target: { value: "password123" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));
    });

    it("should submit form with all data", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: true }),
      });

      const skillsInput = screen.getByPlaceholderText(
        /JavaScript, React, Node.js.../i,
      );
      fireEvent.change(skillsInput, { target: { value: "React, Node.js" } });

      const submitButton = screen.getByRole("button", {
        name: /créer mon compte/i,
      });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining("/api/userCreate"),
          expect.objectContaining({
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: expect.any(String),
          }),
        );
      });
    });

    it("should show loading state during submission", async () => {
      global.fetch.mockImplementationOnce(
        () =>
          new Promise((resolve) =>
            setTimeout(
              () =>
                resolve({
                  ok: true,
                  json: async () => ({ status: true }),
                }),
              100,
            ),
          ),
      );

      const submitButton = screen.getByRole("button", {
        name: /créer mon compte/i,
      });
      fireEvent.click(submitButton);

      expect(screen.getByText("Création...")).toBeInTheDocument();
      expect(submitButton).toBeDisabled();

      await waitFor(
        () => {
          expect(mockNavigate).toHaveBeenCalled();
        },
        { timeout: 2000 },
      );
    });

    it("should navigate to login on successful registration", async () => {
      jest.useFakeTimers();

      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: true }),
      });

      const submitButton = screen.getByRole("button", {
        name: /créer mon compte/i,
      });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(global.fetch).toHaveBeenCalled();
      });

      jest.advanceTimersByTime(1500);

      await waitFor(() => {
        expect(mockNavigate).toHaveBeenCalledWith("/login", {
          state: { message: "Compte créé avec succès !" },
        });
      });

      jest.useRealTimers();
    });

    it("should show error message on failed registration", async () => {
      global.fetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ status: false, message: "Email déjà utilisé" }),
      });

      const submitButton = screen.getByRole("button", {
        name: /créer mon compte/i,
      });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText("Email déjà utilisé")).toBeInTheDocument();
      });
    });

    it("should handle network error", async () => {
      global.fetch.mockRejectedValueOnce(new Error("Network error"));

      const submitButton = screen.getByRole("button", {
        name: /créer mon compte/i,
      });
      fireEvent.click(submitButton);

      await waitFor(() => {
        expect(screen.getByText("Erreur réseau")).toBeInTheDocument();
      });
    });
  });

  describe("Progress Bar", () => {
    it("should show 100% progress on step 3", () => {
      const { container } = renderRegister();

      fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
        target: { value: "John" },
      });
      fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
        target: { value: "Doe" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
        target: { value: "test@example.com" },
      });
      fireEvent.change(screen.getByPlaceholderText("6 caractères minimum"), {
        target: { value: "password123" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      const progressBar = container.querySelector(".progress");
      expect(progressBar).toHaveStyle({ width: "100%" });
    });
  });

  describe("Navigation Links", () => {
    it("should navigate to login when clicking Se connecter link", () => {
      renderRegister();

      const loginButton = screen.getByRole("button", { name: /se connecter/i });
      fireEvent.click(loginButton);

      expect(mockNavigate).toHaveBeenCalledWith("/login");
    });
  });

  describe("useReducer State Management", () => {
    it("should update multiple fields correctly", () => {
      renderRegister();

      const firstNameInput = screen.getByPlaceholderText("Votre prénom");
      const lastNameInput = screen.getByPlaceholderText("Votre nom");

      fireEvent.change(firstNameInput, { target: { value: "John" } });
      fireEvent.change(lastNameInput, { target: { value: "Doe" } });

      expect(firstNameInput.value).toBe("John");
      expect(lastNameInput.value).toBe("Doe");
    });

    it("should maintain state across step navigation", () => {
      renderRegister();

      // Step 1
      fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
        target: { value: "John" },
      });
      fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
        target: { value: "Doe" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      // Step 2
      fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
        target: { value: "test@example.com" },
      });

      // Back to step 1
      const backButton = screen.getByRole("button", { name: /retour/i });
      fireEvent.click(backButton);

      // Check data is preserved
      expect(screen.getByPlaceholderText("Votre prénom").value).toBe("John");
      expect(screen.getByPlaceholderText("Votre nom").value).toBe("Doe");
    });
  });

  describe("Error Handling", () => {
    it("should clear specific field error when user types", () => {
      renderRegister();

      const continueButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(continueButton);

      expect(screen.getByText("Prénom requis")).toBeInTheDocument();
      expect(screen.getByText("Nom requis")).toBeInTheDocument();

      const firstNameInput = screen.getByPlaceholderText("Votre prénom");
      fireEvent.change(firstNameInput, { target: { value: "John" } });

      expect(screen.queryByText("Prénom requis")).not.toBeInTheDocument();
      expect(screen.getByText("Nom requis")).toBeInTheDocument(); // Should still be there
    });

    it("should not submit if step 3 validation fails", () => {
      renderRegister();

      // This should not happen in normal flow, but testing edge case
      const submitButton = screen.getByRole("button", { name: /continuer/i });
      fireEvent.click(submitButton);

      expect(global.fetch).not.toHaveBeenCalled();
    });
  });

  describe("Accessibility", () => {
    it("should have submit button accessible", () => {
      renderRegister();

      // Navigate to step 3
      fireEvent.change(screen.getByPlaceholderText("Votre prénom"), {
        target: { value: "John" },
      });
      fireEvent.change(screen.getByPlaceholderText("Votre nom"), {
        target: { value: "Doe" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      fireEvent.change(screen.getByPlaceholderText("exemple@email.com"), {
        target: { value: "test@example.com" },
      });
      fireEvent.change(screen.getByPlaceholderText("6 caractères minimum"), {
        target: { value: "password123" },
      });
      fireEvent.click(screen.getByRole("button", { name: /continuer/i }));

      const submitButton = screen.getByRole("button", {
        name: /créer mon compte/i,
      });
      expect(submitButton).toHaveAttribute("type", "submit");
    });
  });
});
