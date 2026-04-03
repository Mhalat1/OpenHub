// src/JEST/Home.test.jsx
import { render, screen, waitFor, fireEvent } from "@testing-library/react";
import "@testing-library/jest-dom";
import { BrowserRouter } from "react-router-dom";
import Home from "../pages/Home";

// Mock des données
const mockUser = {
  id: 1,
  firstName: "John",
  lastName: "Doe",
  email: "john.doe@example.com",
  availabilityStart: "2024-01-01",
  availabilityEnd: "2024-12-31",
};

const mockSkills = [
  {
    id: 1,
    name: "React",
    description: "Frontend library",
    technoUtilisees: "JavaScript",
    duree: "6 months",
  },
  {
    id: 2,
    name: "Node.js",
    description: "Backend runtime",
    technoUtilisees: "JavaScript",
    duree: "8 months",
  },
  {
    id: 3,
    name: "Python",
    description: "Programming language",
    technoUtilisees: "Python",
    duree: "12 months",
  },
];

const mockProjects = [
  {
    id: 1,
    name: "Project Alpha",
    description: "Alpha project",
    requiredSkills: "React, Node.js",
    startDate: "2024-01-01",
    endDate: "2024-06-30",
  },
  {
    id: 2,
    name: "Project Beta",
    description: "Beta project",
    requiredSkills: "Python",
    startDate: "2024-02-01",
    endDate: "2024-08-31",
  },
];

const mockUserProjects = [
  {
    id: 1,
    name: "Project Alpha",
    description: "Alpha project",
    requiredSkills: "React, Node.js",
    startDate: "2024-01-01",
    endDate: "2024-06-30",
  },
];

const mockAvailableSkills = [
  { id: 4, name: "TypeScript", description: "Typed JavaScript" },
  { id: 5, name: "GraphQL", description: "API query language" },
];

// Mock fetch global
global.fetch = jest.fn();

// Helper pour render avec Router
const renderWithRouter = (component) => {
  return render(<BrowserRouter>{component}</BrowserRouter>);
};

describe("Home Component - Complete Coverage", () => {
  beforeEach(() => {
    jest.clearAllMocks();

    // Mock localStorage
    Storage.prototype.getItem = jest.fn((key) => {
      if (key === "token") return "mock-token-123";
      return null;
    });

    // Mock des appels API par défaut
    global.fetch.mockImplementation((url) => {
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
      if (url.includes("/api/allprojects")) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockProjects),
        });
      }
      if (url.includes("/api/user/projects")) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockUserProjects),
        });
      }
      if (url.includes("/api/skills")) {
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve(mockAvailableSkills),
        });
      }
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve([]),
      });
    });
  });

  describe("Rendu initial et chargement", () => {
    test("affiche le loader pendant le chargement", () => {
      renderWithRouter(<Home />);
      expect(screen.getByText(/Loading user data/i)).toBeInTheDocument();
    });

    test("affiche les informations utilisateur après chargement", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText(/John Doe/i)).toBeInTheDocument();
        expect(screen.getByText(/john.doe@example.com/i)).toBeInTheDocument();
      });
    });

    test("affiche les dates de disponibilité formatées", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText(/01\/01\/2024/)).toBeInTheDocument();
        expect(screen.getByText(/31\/12\/2024/)).toBeInTheDocument();
      });
    });

    test("affiche 'Not set' quand les dates de disponibilité sont null", async () => {
      const userWithoutDates = {
        ...mockUser,
        availabilityStart: null,
        availabilityEnd: null,
      };

      global.fetch.mockImplementation((url) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve(userWithoutDates),
          });
        }
        if (url.includes("/api/user/skills")) {
          return Promise.resolve({
            ok: true,
            json: () => Promise.resolve([]),
          });
        }
        return Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        const notSetElements = screen.getAllByText(/Not set/i);
        expect(notSetElements.length).toBeGreaterThanOrEqual(2);
      });
    });

    test("gère les erreurs API utilisateur", async () => {
      global.fetch.mockImplementation((url) => {
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
          screen.getByText(/Error: User API error: 401/i),
        ).toBeInTheDocument();
      });
    });
  });

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
        expect(screen.getByText("React")).toBeInTheDocument();
        expect(screen.getByText("Node.js")).toBeInTheDocument();
        expect(screen.getByText("Python")).toBeInTheDocument();
      });
    });
  });

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
        // Utiliser getAllByText et prendre le premier élément (le bouton)
        const projectElements = screen.getAllByText("Project Alpha");
        const projectButton = projectElements[0]; // Le premier est le bouton dans la section projets
        expect(projectButton).toBeInTheDocument();
        expect(projectButton).toHaveClass("projectButton");
      });
    });
  });

  describe("Gestion des disponibilités", () => {
    test("affiche une erreur si la date de début est manquante", async () => {
      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText(/John Doe/i)).toBeInTheDocument();
      });

      const updateButton = screen.getByRole("button", {
        name: /Update Availability/i,
      });
      fireEvent.click(updateButton);

      await waitFor(() => {
        expect(
          screen.getByText(/❌ Please enter start date/i),
        ).toBeInTheDocument();
      });
    });

    test("met à jour les disponibilités avec succès", async () => {
      global.fetch.mockImplementation((url, options) => {
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
        expect(screen.getByText(/John Doe/i)).toBeInTheDocument();
      });

      const startDateInput = document.querySelector('input[type="date"]');
      const endDateInput = document.querySelectorAll('input[type="date"]')[1];
      const updateButton = screen.getByRole("button", {
        name: /Update Availability/i,
      });

      fireEvent.change(startDateInput, { target: { value: "2025-01-01" } });
      fireEvent.change(endDateInput, { target: { value: "2025-12-31" } });
      fireEvent.click(updateButton);

      await waitFor(() => {
        expect(
          screen.getByText(/✅ Availability updated successfully!/i),
        ).toBeInTheDocument();
      });
    });
  });

  describe("Gestion des compétences", () => {
    test("ajoute une nouvelle compétence avec succès", async () => {
      global.fetch.mockImplementation((url, options) => {
        if (
          url.includes("/api/user/add/skills") &&
          options?.method === "POST"
        ) {
          return Promise.resolve({
            ok: true,
            json: () =>
              Promise.resolve({ success: true, skill_name: "TypeScript" }),
          });
        }
        if (url.includes("/api/user/skills")) {
          return Promise.resolve({
            ok: true,
            json: () =>
              Promise.resolve([...mockSkills, { id: 4, name: "TypeScript" }]),
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
          json: () => Promise.resolve(mockAvailableSkills),
        });
      });

      renderWithRouter(<Home />);

      await waitFor(() => {
        expect(screen.getByText(/John Doe/i)).toBeInTheDocument();
      });

      const selects = screen.getAllByRole("combobox");
      const skillSelect = selects[0];
      const addButton = screen.getByRole("button", { name: /\+ Add Skill/i });

      fireEvent.change(skillSelect, { target: { value: "4" } });
      fireEvent.click(addButton);

      await waitFor(() => {
        expect(
          screen.getByText(/✅ TypeScript added successfully/i),
        ).toBeInTheDocument();
      });
    });
  });
});
