// src/JEST/Messages.test.jsx
import "@testing-library/jest-dom";
import {
    fireEvent,
    render,
    screen,
    waitFor
} from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import Messages from "../pages/Messages";

// Mock de l'URL de l'API
const API_URL = "http://localhost:3000";

// Mock de localStorage
const mockLocalStorage = {
  store: {},
  setItem: jest.fn((key, value) => {
    mockLocalStorage.store[key] = value;
  }),
  getItem: jest.fn((key) => {
    return mockLocalStorage.store[key] || null;
  }),
  removeItem: jest.fn((key) => {
    delete mockLocalStorage.store[key];
  }),
  clear: jest.fn(() => {
    mockLocalStorage.store = {};
  }),
};

// Mock de fetch
global.fetch = jest.fn();

// Mock de window.confirm
global.confirm = jest.fn(() => true);

// Mock de import.meta.env
jest.mock("../pages/Messages", () => {
  const actual = jest.requireActual("../pages/Messages");
  return {
    __esModule: true,
    default: actual.default,
  };
});

describe("Messages Component", () => {
  beforeEach(() => {
    // Reset des mocks
    mockLocalStorage.store = {};
    global.fetch.mockClear();
    global.confirm.mockClear();

    // Définit window.localStorage
    Object.defineProperty(window, "localStorage", {
      value: mockLocalStorage,
      writable: true,
    });

    // Simule un utilisateur connecté avec token
    mockLocalStorage.store = {
      token: "test-token-123",
      user_email: "user@user",
    };
  });

  // Helper pour mock les réponses API
  const mockApiResponses = (overrides = {}) => {
    const defaultResponses = {
      "/api/getConnectedUser": {
        id: 1,
        firstName: "John",
        lastName: "Doe",
        email: "john@example.com",
      },
      "/api/user/friends": [
        {
          id: 2,
          firstName: "Alice",
          lastName: "Smith",
          email: "alice@example.com",
        },
        {
          id: 3,
          firstName: "Bob",
          lastName: "Johnson",
          email: "bob@example.com",
        },
      ],
      "/api/get/conversations": [
        {
          id: 1,
          title: "Project Discussion",
          description: "Discussion about the new project",
          createdById: 1,
        },
        {
          id: 2,
          title: "Team Chat",
          description: "General team chat",
          createdById: 2,
        },
      ],
      "/api/get/messages": {
        data: [
          {
            id: 1,
            content: "Hello everyone!",
            conversationId: 1,
            authorId: 1,
            authorName: "John Doe",
            createdAt: "2024-01-15T10:30:00Z",
          },
          {
            id: 2,
            content: "Hi John!",
            conversationId: 1,
            authorId: 2,
            authorName: "Alice Smith",
            createdAt: "2024-01-15T10:35:00Z",
          },
        ],
      },
    };

    global.fetch.mockImplementation((url, options) => {
      console.log(`🌐 API appelée: ${url}`);

      const responses = { ...defaultResponses, ...overrides };

      for (const [endpoint, data] of Object.entries(responses)) {
        if (url.includes(endpoint)) {
          return Promise.resolve({
            ok: true,
            json: async () => data,
          });
        }
      }

      if (url.includes("/api/create/") || url.includes("/api/delete/")) {
        return Promise.resolve({
          ok: true,
          json: async () => ({
            success: true,
            message: "Operation successful",
          }),
        });
      }

      return Promise.resolve({
        ok: false,
        status: 404,
        json: async () => ({ error: "Endpoint not found" }),
      });
    });
  };

  test("1. Affiche le chargement initial", () => {
    global.fetch.mockImplementation(() => new Promise(() => {}));

    render(
      <MemoryRouter>
        <Messages />
      </MemoryRouter>,
    );

    expect(screen.getByText ("Loading..."))[0].toBeInTheDocument();
  });

  test("3. Affiche un message d'erreur sans token", async () => {
    mockLocalStorage.store = {};

    render(
      <MemoryRouter>
        <Messages />
      </MemoryRouter>,
    );

    await waitFor(
      () => {
        expect(
          screen.getByText (/Please log in to view messages/i),
        ).toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    console.log("✅ Message d'erreur affiché sans token");
  });

  test("4. Ouvre et ferme une conversation", async () => {
    mockApiResponses();

    render(
      <MemoryRouter>
        <Messages />
      </MemoryRouter>,
    );

    await waitFor(
      () => {
        expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    const toggleButtons = screen.getByText ("▼");
    fireEvent.click(toggleButtons[0]);

    await waitFor(
      () => {
        expect(screen.getByText ("Hello everyone!"))[0].toBeInTheDocument();
        expect(screen.getByText ("Hi John!"))[0].toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    fireEvent.click(toggleButtons[0]);

    await waitFor(
      () => {
        expect(screen.queryByText("Hello everyone!")).not.toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    console.log("✅ Ouverture/fermeture conversation fonctionne");
  });

  test("5. Crée une nouvelle conversation", async () => {
    global.fetch
      .mockImplementationOnce(() =>
        Promise.resolve({
          ok: true,
          json: () =>
            Promise.resolve({
              id: 1,
              firstName: "John",
              lastName: "Doe",
              email: "john@example.com",
            }),
        }),
      )
      .mockImplementationOnce(() =>
        Promise.resolve({
          ok: true,
          json: () =>
            Promise.resolve([
              {
                id: 2,
                firstName: "Alice",
                lastName: "Smith",
                email: "alice@example.com",
              },
              {
                id: 3,
                firstName: "Bob",
                lastName: "Johnson",
                email: "bob@example.com",
              },
            ]),
        }),
      )
      .mockImplementationOnce(() =>
        Promise.resolve({
          ok: true,
          json: () => Promise.resolve([]),
        }),
      )
      .mockImplementationOnce(() =>
        Promise.resolve({
          ok: true,
          json: () => Promise.resolve({ data: [] }),
        }),
      )
      .mockImplementationOnce((url, options) => {
        console.log("Appel création conversation:", url, options?.method);
        return Promise.resolve({
          ok: true,
          json: () =>
            Promise.resolve({
              success: true,
              id: 999,
              message: "Conversation created",
            }),
        });
      });

    render(
      <MemoryRouter>
        <Messages />
      </MemoryRouter>,
    );

    await waitFor(
      () => {
        expect(screen.queryByText("Loading...")).not.toBeInTheDocument();
      },
      { timeout: 5000 },
    );

    const titleInput = await screen.findByPlaceholderText(
      "Title (2-255 characters)",
    );
    fireEvent.change(titleInput, { target: { value: "Test Conversation" } });

    const descInput = screen.getByPlaceholderText(
      "Description (optional, max 1000 characters)",
    );
    fireEvent.change(descInput, { target: { value: "Test description" } });

    const checkboxes = screen.getAllByRole("checkbox");
    fireEvent.click(checkboxes[0]);

    const createButton = screen.getByText ("Create");
    await waitFor(
      () => {
        expect(createButton).not.toBeDisabled();
      },
      { timeout: 2000 },
    );

    fireEvent.click(createButton);

    await waitFor(
      () => {
        const fetchCalls = global.fetch.mock.calls;
        const createCalls = fetchCalls.filter(
          (call) =>
            call[0] &&
            typeof call[0] === "string" &&
            call[0].includes("/api/create/conversation"),
        );
        expect(createCalls.length).toBe(1);
      },
      { timeout: 5000 },
    );

    console.log("✅ Test 5 passé");
  }, 10000);

  test("6. Envoie un message dans une conversation", async () => {
    mockApiResponses();

    render(
      <MemoryRouter>
        <Messages />
      </MemoryRouter>,
    );

    await waitFor(
      () => {
        expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    const toggleButtons = screen.getByText ("▼");
    fireEvent.click(toggleButtons[0]);

    await waitFor(
      () => {
        expect(
          screen.getByPlaceholderText(/Type your message/i),
        ).toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    const textarea = screen.getByPlaceholderText(/Type your message/i);
    fireEvent.change(textarea, {
      target: { value: "Test message from Jest!" },
    });

    const sendButton = screen.getByText ("Send");
    fireEvent.click(sendButton);

    await waitFor(
      () => {
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining("/api/create/message"),
          expect.objectContaining({
            method: "POST",
            body: JSON.stringify({
              content: "Test message from Jest!",
              conversation_id: 1,
            }),
          }),
        );
      },
      { timeout: 3000 },
    );

    console.log("✅ Envoi de message testé");
  });

  test("7. Supprime une conversation (confirmation)", async () => {
    mockApiResponses();

    render(
      <MemoryRouter>
        <Messages />
      </MemoryRouter>,
    );

    await waitFor(
      () => {
        expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
      },
      { timeout: 3000 },
    );

    const deleteButtons = screen.getByText ("🗑");
    fireEvent.click(deleteButtons[0]);

    expect(global.confirm).toHaveBeenCalledWith("Delete this conversation?");

    await waitFor(
      () => {
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining("/api/delete/conversation/1"),
          expect.objectContaining({
            method: "DELETE",
          }),
        );
      },
      { timeout: 3000 },
    );

    console.log("✅ Suppression conversation testée");
  });

  // ==================== NOUVEAUX TESTS CORRIGÉS ====================

  describe("Tests additionnels pour Messages", () => {
    test("11. Gère les erreurs 401 (session expirée)", async () => {
      global.fetch.mockImplementation((url) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            status: 401,
            ok: false,
            json: async () => ({ message: "Unauthorized" }),
          });
        }
        return Promise.resolve({
          ok: true,
          json: async () => ({}),
        });
      });

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      // Le composant affiche "Please log in to view messages" quand l'utilisateur n'est pas connecté
      await waitFor(
        () => {
          expect(
            screen.getByText (/Please log in to view messages/i),
          ).toBeInTheDocument();
        },
        { timeout: 3000 },
      );
    });

    test("12. Affiche une liste d'amis vide", async () => {
      global.fetch.mockImplementation((url) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ id: 1, firstName: "John", lastName: "Doe" }),
          });
        }
        if (url.includes("/api/user/friends")) {
          return Promise.resolve({
            ok: true,
            json: async () => [],
          });
        }
        if (url.includes("/api/get/conversations")) {
          return Promise.resolve({
            ok: true,
            json: async () => [],
          });
        }
        if (url.includes("/api/get/messages")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ data: [] }),
          });
        }
        return Promise.resolve({
          ok: true,
          json: async () => ({}),
        });
      });

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText (/Friends \(0\)/i))[0].toBeInTheDocument();
          expect(screen.getByText ("No friends available"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );
    });

    test("13. Affiche une liste de conversations vide", async () => {
      global.fetch.mockImplementation((url) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ id: 1, firstName: "John", lastName: "Doe" }),
          });
        }
        if (url.includes("/api/user/friends")) {
          return Promise.resolve({
            ok: true,
            json: async () => [
              { id: 2, firstName: "Alice", lastName: "Smith" },
            ],
          });
        }
        if (url.includes("/api/get/conversations")) {
          return Promise.resolve({
            ok: true,
            json: async () => [],
          });
        }
        if (url.includes("/api/get/messages")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ data: [] }),
          });
        }
        return Promise.resolve({
          ok: true,
          json: async () => ({}),
        });
      });

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText (/Conversations \(0\)/i))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      expect(screen.queryByText("Project Discussion")).not.toBeInTheDocument();
    });

    test("14. Validation du titre de conversation trop court", async () => {
      mockApiResponses();

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText ("New Conversation"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const titleInput = screen.getByPlaceholderText(
        "Title (2-255 characters)",
      );
      const createButton = screen.getByText ("Create");

      fireEvent.change(titleInput, { target: { value: "A" } });

      const checkboxes = screen.getAllByRole("checkbox");
      fireEvent.click(checkboxes[0]);

      fireEvent.click(createButton);

      await waitFor(
        () => {
          expect(
            screen.getByText (/Title must be between 2 and 255 characters/i),
          ).toBeInTheDocument();
        },
        { timeout: 3000 },
      );
    });

    test("15. Validation du titre de conversation trop long", async () => {
      mockApiResponses();

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText ("New Conversation"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const titleInput = screen.getByPlaceholderText(
        "Title (2-255 characters)",
      );
      const createButton = screen.getByText ("Create");

      fireEvent.change(titleInput, { target: { value: "A".repeat(256) } });

      const checkboxes = screen.getAllByRole("checkbox");
      fireEvent.click(checkboxes[0]);

      fireEvent.click(createButton);

      await waitFor(
        () => {
          expect(
            screen.getByText (/Title must be between 2 and 255 characters/i),
          ).toBeInTheDocument();
        },
        { timeout: 3000 },
      );
    });

    test("17. Validation du message trop long", async () => {
      mockApiResponses();

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const toggleButtons = screen.getByText ("▼");
      fireEvent.click(toggleButtons[0]);

      await waitFor(
        () => {
          expect(
            screen.getByPlaceholderText(/Type your message/i),
          ).toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const textarea = screen.getByPlaceholderText(/Type your message/i);
      const sendButton = screen.getByText ("Send");

      fireEvent.change(textarea, { target: { value: "A".repeat(251) } });
      fireEvent.click(sendButton);

      await waitFor(
        () => {
          expect(
            screen.getByText (/Message cannot exceed 250 characters/i),
          ).toBeInTheDocument();
        },
        { timeout: 3000 },
      );
    });

    test("18. Supprime un message avec confirmation", async () => {
      mockApiResponses();

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const toggleButtons = screen.getByText ("▼");
      fireEvent.click(toggleButtons[0]);

      await waitFor(
        () => {
          expect(screen.getByText ("Hello everyone!"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const deleteMsgButtons = screen.getByText ("🗑");
      // Le premier bouton est pour la conversation, le second pour le message
      fireEvent.click(deleteMsgButtons[1]);

      expect(global.confirm).toHaveBeenCalledWith("Delete this message?");

      await waitFor(
        () => {
          expect(global.fetch).toHaveBeenCalledWith(
            expect.stringContaining("/api/delete/message/1"),
            expect.objectContaining({ method: "DELETE" }),
          );
        },
        { timeout: 3000 },
      );
    });

    test("19. Annule la suppression d'un message", async () => {
      global.confirm.mockReturnValueOnce(false);

      mockApiResponses();

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const toggleButtons = screen.getByText ("▼");
      fireEvent.click(toggleButtons[0]);

      await waitFor(
        () => {
          expect(screen.getByText ("Hello everyone!"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const deleteMsgButtons = screen.getByText ("🗑");
      fireEvent.click(deleteMsgButtons[1]);

      expect(global.confirm).toHaveBeenCalledWith("Delete this message?");

      expect(global.fetch).not.toHaveBeenCalledWith(
        expect.stringContaining("/api/delete/message/"),
        expect.anything(),
      );
    });

    test("20. Gère les erreurs réseau lors de la création de conversation", async () => {
      global.fetch.mockImplementation((url, options) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ id: 1, firstName: "John", lastName: "Doe" }),
          });
        }
        if (url.includes("/api/user/friends")) {
          return Promise.resolve({
            ok: true,
            json: async () => [
              { id: 2, firstName: "Alice", lastName: "Smith" },
            ],
          });
        }
        if (url.includes("/api/get/conversations")) {
          return Promise.resolve({
            ok: true,
            json: async () => [],
          });
        }
        if (url.includes("/api/get/messages")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ data: [] }),
          });
        }
        if (url.includes("/api/create/conversation")) {
          return Promise.reject(new Error("Network error"));
        }
        return Promise.resolve({
          ok: true,
          json: async () => ({}),
        });
      });

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );

      await waitFor(
        () => {
          expect(screen.getByText ("New Conversation"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const titleInput = screen.getByPlaceholderText(
        "Title (2-255 characters)",
      );
      fireEvent.change(titleInput, { target: { value: "Test Conversation" } });

      const checkboxes = screen.getAllByRole("checkbox");
      fireEvent.click(checkboxes[0]);

      const createButton = screen.getByText ("Create");
      fireEvent.click(createButton);

      await waitFor(
        () => {
          expect(screen.getByText (/Network error/i))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );
    });

    test("21. Gère les erreurs réseau lors de l'envoi de message", async () => {
      global.fetch.mockImplementation((url, options) => {
        if (url.includes("/api/getConnectedUser")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ id: 1, firstName: "John", lastName: "Doe" }),
          });
        }
        if (url.includes("/api/user/friends")) {
          return Promise.resolve({
            ok: true,
            json: async () => [
              { id: 2, firstName: "Alice", lastName: "Smith" },
            ],
          });
        }
        if (url.includes("/api/get/conversations")) {
          return Promise.resolve({
            ok: true,
            json: async () => [
              {
                id: 1,
                title: "Project Discussion",
                description: "Discussion about the new project",
                createdById: 1,
              },
            ],
          });
        }
        if (url.includes("/api/get/messages")) {
          return Promise.resolve({
            ok: true,
            json: async () => ({ data: [] }),
          });
        }
        if (url.includes("/api/create/message")) {
          return Promise.reject(new Error("Network error"));
        }
        return Promise.resolve({
          ok: true,
          json: async () => ({}),
        });
      });

      render(
        <MemoryRouter>
          <Messages />
        </MemoryRouter>,
      );
      await waitFor(
        () => {
          expect(screen.getByText ("Project Discussion"))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const toggleButtons = screen.getByText ("▼");
      fireEvent.click(toggleButtons[0]);
      await waitFor(
        () => {
          expect(
            screen.getByPlaceholderText(/Type your message/i),
          ).toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      const textarea = screen.getByPlaceholderText(/Type your message/i);
      const sendButton = screen.getByText ("Send");
      fireEvent.change(textarea, {
        target: { value: "Test message from Jest!" },
      });
      fireEvent.click(sendButton);
      await waitFor(
        () => {
          expect(screen.getByText (/Network error/i))[0].toBeInTheDocument();
        },
        { timeout: 3000 },
      );

      console.log("✅ Tests additionnels pour Messages passés");
    });
  });
});
