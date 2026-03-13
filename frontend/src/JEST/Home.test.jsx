process.env.VITE_API_URL = "http://localhost:3000";
import "@testing-library/jest-dom";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { BrowserRouter } from "react-router-dom";
import Home from "../pages/Home";

// ===== MOCKS =====
global.fetch = jest.fn();
global.console.error = jest.fn();
global.confirm = jest.fn();

const mockLocalStorage = (() => {
  let store = {};
  return {
    getItem: jest.fn((key) => store[key] || null),
    setItem: jest.fn((key, value) => {
      store[key] = value.toString();
    }),
    clear: jest.fn(() => {
      store = {};
    }),
  };
})();
Object.defineProperty(window, "localStorage", { value: mockLocalStorage });

// ===== HELPERS =====
const renderWithRouter = (component) => {
  return render(<BrowserRouter>{component}</BrowserRouter>);
};

// ===== MOCK DATA =====
const mockToken = "mock-jwt-token-123";

const mockUser = {
  id: 1,
  firstName: "John",
  lastName: "Doe",
  email: "john.doe@example.com",
  availabilityStart: "2024-01-01T00:00:00.000Z",
  availabilityEnd: "2024-12-31T00:00:00.000Z",
};

const mockUserNoAvailability = {
  firstName: "Jane",
  lastName: "Smith",
  email: "jane.smith@example.com",
  availabilityStart: null,
  availabilityEnd: null,
};

const mockSkills = [
  {
    id: 1,
    name: "React",
    description: "Frontend framework",
    duree: "2 years",
    technoUtilisees: "JavaScript, JSX",
  },
  {
    id: 2,
    name: "Node.js",
    description: "Backend runtime",
    duree: "1 year",
    technoUtilisees: "JavaScript",
  },
  {
    id: 3,
    name: "Python",
    description: "Programming language",
    duree: "3 years",
    technoUtilisees: "Django, Flask",
  },
];

const mockProjects = [
  {
    id: 1,
    name: "Project Alpha",
    description: "Test project description",
    requiredSkills: "React, Node.js",
    startDate: "2024-01-01T00:00:00.000Z",
    endDate: "2024-06-01T00:00:00.000Z",
  },
  {
    id: 2,
    name: "Project Beta",
    description: "Another project",
    requiredSkills: "Python",
    startDate: "2024-02-01T00:00:00.000Z",
    endDate: "2024-08-01T00:00:00.000Z",
  },
];

// ===== SETUP DEFAULT MOCKS =====
const setupDefaultMocks = () => {
  mockLocalStorage.getItem.mockReturnValue(mockToken);

  fetch.mockImplementation((url) => {
    if (url.includes("/api/getConnectedUser")) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockUser),
      });
    }
    if (url.includes("/api/user/skills")) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockSkills),
      });
    }
    if (url.includes("/api/skills")) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockSkills),
      });
    }
    if (url.includes("/api/allprojects")) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockProjects),
      });
    }
    if (url.includes("/api/user/projects")) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([mockProjects[0]]),
      });
    }
    return Promise.reject(new Error("Unknown endpoint"));
  });
};

// ===== TESTS =====
describe("Home Component - Complete Coverage", () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockLocalStorage.clear();
    setupDefaultMocks();
  });

  // ==================== RENDU INITIAL ====================
  describe("Rendu initial et chargement", () => {
    test("affiche le loader pendant le chargement", () => {
      renderWithRouter(<Home />);
      expect(screen.getByText(/Loading user data.../i)).toBeInTheDocument();
    });

    test("affiche les informations utilisateur après chargement", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
        expect(screen.getByText("john.doe@example.com")).toBeInTheDocument();
      });
    });

    test("affiche les dates de disponibilité formatées", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("01/01/2024")).toBeInTheDocument();
        expect(screen.getByText("31/12/2024")).toBeInTheDocument();
      });
    });

    test("affiche 'Not set' quand les dates de disponibilité sont null", async () => {
      fetch.mockImplementation((url) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUserNoAvailability),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        // "Not set" appears twice (start date + end date both null)
        expect(screen.getAllByText("Not set")).toHaveLength(2);
      });
    });

    test("gère les erreurs API utilisateur", async () => {
      fetch.mockImplementation((url) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: false,
            status: 401,
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(
          screen.getByText (/Error: User API error: 401/i),
        ).toBeInTheDocument();
      });
    });
  });

  // ==================== AFFICHAGE DES COMPÉTENCES ====================
  describe("Affichage des compétences", () => {
    test("affiche le nombre correct de compétences", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText(/My Skills \(3\)/i)).toBeInTheDocument();
      });
    });

    test("affiche toutes les compétences de l'utilisateur", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        const skillButtons = screen
          .getAllByRole("button")
          .filter((button) => button.className.includes("skillButton"));
        expect(skillButtons.length).toBe(3);
        expect(skillButtons[0]).toHaveTextContent("React");
        expect(skillButtons[1]).toHaveTextContent("Node.js");
        expect(skillButtons[2]).toHaveTextContent("Python");
      });
    });

    test("affiche un message si aucune compétence", async () => {
      fetch.mockImplementation((url) => {
        if (url.includes("/api/user/skills")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve([]),
          });
        }
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUser),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("No skills added yet")).toBeInTheDocument();
      });
    });
  });

  // ==================== AFFICHAGE DES PROJETS ====================
  describe("Affichage des projets", () => {
    test("affiche le nombre correct de projets", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText(/My Projects \(1\)/i)).toBeInTheDocument();
      });
    });

    test("affiche les projets de l'utilisateur", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        const projectButtons = screen
          .getAllByRole("button")
          .filter((button) => button.className.includes("projectButton"));
        expect(projectButtons.length).toBe(1);
        expect(projectButtons[0]).toHaveTextContent("Project Alpha");
      });
    });

    test("affiche un message si aucun projet", async () => {
      fetch.mockImplementation((url) => {
        if (url.includes("/api/user/projects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve([]),
          });
        }
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUser),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("No projects yet")).toBeInTheDocument();
      });
    });
  });

  // ==================== AJOUT DE COMPÉTENCES ====================
  describe("Ajout de compétences", () => {
    test("permet de sélectionner et d'ajouter une compétence", async () => {
      fetch.mockImplementation((url, options) => {
        if (
          url.includes("/api/user/add/skills") &&
          options?.method === "POST"
        ) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true, skill_name: "React" }),
          });
        }
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUser),
          });
        }
        if (url.includes("/api/user/skills")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockSkills),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const selects = screen.getAllByRole("combobox");
      const skillSelect = selects[0];

      fireEvent.change(skillSelect, { target: { value: "1" } });

      const addButton = screen.getByText ("+ Add Skill");
      fireEvent.click(addButton);

      await waitFor(() => {
        expect(
          screen.getByText (/React added successfully/i),
        ).toBeInTheDocument();
      });
    });

    test("bouton désactivé si aucune compétence sélectionnée", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const addButton = screen.getByText ("+ Add Skill");
      expect(addButton).toBeDisabled();
    });
  });

  // ==================== AJOUT DE PROJETS ====================
  describe("Ajout de projets", () => {
    test("permet de rejoindre un projet", async () => {
      fetch.mockImplementation((url, options) => {
        if (
          url.includes("/api/user/add/project") &&
          options?.method === "POST"
        ) {
          return Promise.resolve({
            ok: true,
            json: () =>
              Promise.resolve({ success: true, project_name: "Project Alpha" }),
          });
        }
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUser),
          });
        }
        if (url.includes("/api/user/projects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve([mockProjects[0]]),
          });
        }
        if (url.includes("/api/allprojects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockProjects),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockSkills),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const selects = screen.getAllByRole("combobox");
      const projectSelect = selects[1];

      fireEvent.change(projectSelect, { target: { value: "1" } });

      const joinButton = screen.getByText (/Join Project/i);
      fireEvent.click(joinButton);

      await waitFor(() => {
        expect(
          screen.getByText (/Project Alpha added successfully/i),
        ).toBeInTheDocument();
      });
    });

    test("bouton désactivé si aucun projet sélectionné", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const joinButton = screen.getByText (/Join Project/i);
      expect(joinButton).toBeDisabled();
    });
  });

  // ==================== GESTION DES DISPONIBILITÉS ====================
  describe("Gestion des disponibilités", () => {
    test("permet de mettre à jour les disponibilités", async () => {
      fetch.mockImplementation((url, options) => {
        if (
          url.includes("/api/user/availability") &&
          options?.method === "POST"
        ) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true }),
          });
        }
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUser),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const dateInputs = screen.getAllByDisplayValue("");
      const startDateInput = dateInputs[0];
      const endDateInput = dateInputs[1];

      fireEvent.change(startDateInput, { target: { value: "2024-01-01" } });
      fireEvent.change(endDateInput, { target: { value: "2024-12-31" } });

      const updateButton = screen.getByText ("Update Availability");
      fireEvent.click(updateButton);

      await waitFor(() => {
        expect(
          screen.getByText (/Availability updated successfully/i),
        ).toBeInTheDocument();
      });
    });

    test("affiche une erreur si la date de début est manquante", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const dateInputs = screen.getAllByDisplayValue("");
      const endDateInput = dateInputs[1];

      fireEvent.change(endDateInput, { target: { value: "2024-12-31" } });

      const updateButton = screen.getByText ("Update Availability");
      fireEvent.click(updateButton);

      await waitFor(() => {
        expect(
          screen.getByText (/Please enter start date/i),
        ).toBeInTheDocument();
      });
    });
  });

  // ==================== MODALS ====================
  describe("Modals", () => {
    test("ouvre le modal de compétence au clic", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      // Attendre que les compétences soient chargées
      await waitFor(() => {
        const skillButtons = screen
          .getAllByRole("button")
          .filter(
            (button) =>
              button.className.includes("skillButton") &&
              button.textContent === "React",
          );
        expect(skillButtons.length).toBe(1);
      });

      const skillButtons = screen
        .getAllByRole("button")
        .filter(
          (button) =>
            button.className.includes("skillButton") &&
            button.textContent === "React",
        );
      fireEvent.click(skillButtons[0]);

      // Vérifier que le modal est ouvert
      await waitFor(() => {
        expect(
          screen.getByRole("heading", { level: 2, name: "React" }),
        ).toBeInTheDocument();
        expect(screen.getByText("Frontend framework")).toBeInTheDocument();
      });
    });

    test("ouvre le modal de projet au clic", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      // Attendre que les projets soient chargés
      await waitFor(() => {
        const projectButtons = screen
          .getAllByRole("button")
          .filter(
            (button) =>
              button.className.includes("projectButton") &&
              button.textContent === "Project Alpha",
          );
        expect(projectButtons.length).toBe(1);
      });

      const projectButtons = screen
        .getAllByRole("button")
        .filter(
          (button) =>
            button.className.includes("projectButton") &&
            button.textContent === "Project Alpha",
        );
      fireEvent.click(projectButtons[0]);

      // Vérifier que le modal est ouvert
      await waitFor(() => {
        expect(
          screen.getByRole("heading", { level: 2, name: "Project Alpha" }),
        ).toBeInTheDocument();
        expect(
          screen.getByText ("Test project description"),
        ).toBeInTheDocument();
      });
    });

    test("ferme le modal avec le bouton close", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      // Ouvrir le modal
      await waitFor(() => {
        const skillButtons = screen
          .getAllByRole("button")
          .filter(
            (button) =>
              button.className.includes("skillButton") &&
              button.textContent === "React",
          );
        expect(skillButtons.length).toBe(1);
      });

      const skillButtons = screen
        .getAllByRole("button")
        .filter(
          (button) =>
            button.className.includes("skillButton") &&
            button.textContent === "React",
        );
      fireEvent.click(skillButtons[0]);

      // Vérifier que le modal est ouvert
      await waitFor(() => {
        expect(
          screen.getByRole("heading", { level: 2, name: "React" }),
        ).toBeInTheDocument();
      });

      // Fermer le modal
      const closeButton = screen.getByText ("×");
      fireEvent.click(closeButton);

      // Vérifier que le modal est fermé
      await waitFor(() => {
        expect(
          screen.queryByRole("heading", { level: 2, name: "React" }),
        ).not.toBeInTheDocument();
      });
    });

    test("supprime une compétence avec confirmation", async () => {
      global.confirm.mockReturnValue(true);

      // Configuration unique des mocks pour ce test
      const mockFetch = jest.fn((url, options) => {
        // User info
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockUser),
          });
        }
        // Compétences initiales (avant suppression)
        if (url.includes("/api/user/skills") && !options?.method) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockSkills),
          });
        }
        // Compétences disponibles
        if (url.includes("/api/skills")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockSkills),
          });
        }
        // Tous les projets
        if (url.includes("/api/allprojects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockProjects),
          });
        }
        // Projets de l'utilisateur
        if (url.includes("/api/user/projects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve([mockProjects[0]]),
          });
        }
        // Suppression de compétence
        if (
          url.includes("/api/user/delete/skill") &&
          options?.method === "DELETE"
        ) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true, skill_name: "React" }),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      fetch.mockImplementation(mockFetch);

      renderWithRouter(<Home />);

      // Attendre que l'utilisateur soit chargé
      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      // Mettre à jour le mock pour les appels après suppression
      mockFetch.mockImplementation((url, options) => {
        // Compétences après suppression (sans React)
        if (url.includes("/api/user/skills") && !options?.method) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockSkills.filter((s) => s.id !== 1)),
          });
        }
        // Compétences disponibles
        if (url.includes("/api/skills")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockSkills),
          });
        }
        // Tous les projets
        if (url.includes("/api/allprojects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(mockProjects),
          });
        }
        // Projets de l'utilisateur
        if (url.includes("/api/user/projects")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve([mockProjects[0]]),
          });
        }
        // Suppression de compétence
        if (
          url.includes("/api/user/delete/skill") &&
          options?.method === "DELETE"
        ) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve({ success: true, skill_name: "React" }),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      // Vérifier que React n'est plus dans la liste des compétences
      await waitFor(
        () => {
          const reactButtonsAfter = screen
            .getAllByRole("button")
            .filter(
              (button) =>
                button.className.includes("skillButton") &&
                button.textContent === "React",
            );
          expect(reactButtonsAfter.length).toBe(0);
        },
        { timeout: 3000 },
      );
    });
  });

  // ==================== LIENS ET NAVIGATION ====================
  describe("Liens et navigation", () => {
    test("affiche le lien de don", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText("John Doe")).toBeInTheDocument();
      });

      const donateLink = screen.getByText ("💖 Faire un don");
      expect(donateLink).toBeInTheDocument();
      expect(donateLink.closest("a")).toHaveAttribute("href", "/donate");
    });
  });
});