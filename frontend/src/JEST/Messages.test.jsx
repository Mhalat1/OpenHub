import "@testing-library/jest-dom";
import { fireEvent, render, screen, waitFor } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import Messages from "../pages/Messages";

global.fetch = jest.fn();
global.confirm = jest.fn(() => true);

const mockLocalStorage = {
  store: { token: "test-token-123", user_email: "user@user" },
  getItem: jest.fn((key) => mockLocalStorage.store[key] || null),
  setItem: jest.fn((key, value) => {
    mockLocalStorage.store[key] = value;
  }),
  removeItem: jest.fn((key) => {
    delete mockLocalStorage.store[key];
  }),
  clear: jest.fn(() => {
    mockLocalStorage.store = {};
  }),
};

beforeEach(() => {
  global.fetch.mockClear();
  global.confirm.mockClear();
  mockLocalStorage.store = { token: "test-token-123", user_email: "user@user" };
  Object.defineProperty(window, "localStorage", {
    value: mockLocalStorage,
    writable: true,
  });
});

const setup = () =>
  render(
    <MemoryRouter>
      <Messages />
    </MemoryRouter>,
  );

const mockApi = (overrides = {}) => {
  const defaults = {
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
        description: "About the project",
        createdById: 1,
      },
      {
        id: 2,
        title: "Team Chat",
        description: "General chat",
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

  global.fetch.mockImplementation((url) => {
    const responses = { ...defaults, ...overrides };
    for (const [endpoint, data] of Object.entries(responses)) {
      if (url.includes(endpoint)) {
        // Si data est une fonction (ex: () => Promise.reject(...)), on l'appelle
        return typeof data === "function"
          ? data()
          : Promise.resolve({ ok: true, json: async () => data });
      }
    }
    if (url.includes("/api/create/") || url.includes("/api/delete/"))
      return Promise.resolve({
        ok: true,
        json: async () => ({ success: true }),
      });
    return Promise.resolve({
      ok: false,
      status: 404,
      json: async () => ({ error: "Not found" }),
    });
  });
};

const openFirstConversation = async () => {
  await waitFor(
    () => expect(screen.getByText("Project Discussion")).toBeInTheDocument(),
    { timeout: 3000 },
  );
  fireEvent.click(screen.getAllByText("▼")[0]);
};

// Matcher pour texte fragmenté dans le DOM (ex: "Friends (\n  0\n)")
const textMatch = (text) => (_, el) =>
  el?.tagName === "H2" && el?.textContent?.replace(/\s+/g, " ").trim() === text;

describe("Messages Component", () => {
  test("affiche le chargement initial", () => {
    global.fetch.mockImplementation(() => new Promise(() => {}));
    setup();
    expect(screen.getByText("Loading...")).toBeInTheDocument();
  });

  test("affiche une erreur sans token", async () => {
    mockLocalStorage.store = {};
    setup();
    await waitFor(
      () =>
        expect(
          screen.getByText(/Please log in to view messages/i),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("erreur 401 → affiche please log in", async () => {
    global.fetch.mockImplementation((url) => {
      if (url.includes("/api/getConnectedUser"))
        return Promise.resolve({
          ok: false,
          status: 401,
          json: async () => ({ message: "Unauthorized" }),
        });
      return Promise.resolve({ ok: true, json: async () => ({}) });
    });
    setup();
    await waitFor(
      () =>
        expect(
          screen.getByText(/Please log in to view messages/i),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("ouvre et ferme une conversation", async () => {
    mockApi();
    setup();
    await openFirstConversation();
    await waitFor(
      () => expect(screen.getByText("Hello everyone!")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.click(screen.getAllByText("▼")[0]);
    await waitFor(
      () =>
        expect(screen.queryByText("Hello everyone!")).not.toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("envoie un message", async () => {
    mockApi();
    setup();
    await openFirstConversation();
    await waitFor(
      () =>
        expect(
          screen.getByPlaceholderText(/Type your message/i),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.change(screen.getByPlaceholderText(/Type your message/i), {
      target: { value: "Test message!" },
    });
    fireEvent.click(screen.getByText("Send"));
    await waitFor(
      () =>
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining("/api/create/message"),
          expect.objectContaining({
            method: "POST",
            body: JSON.stringify({
              content: "Test message!",
              conversation_id: 1,
            }),
          }),
        ),
      { timeout: 3000 },
    );
  });

  test("supprime une conversation avec confirmation", async () => {
    mockApi();
    setup();
    await waitFor(
      () => expect(screen.getByText("Project Discussion")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.click(screen.getAllByText("🗑")[0]);
    expect(global.confirm).toHaveBeenCalledWith("Delete this conversation?");
    await waitFor(
      () =>
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining("/api/delete/conversation/1"),
          expect.objectContaining({ method: "DELETE" }),
        ),
      { timeout: 3000 },
    );
  });

  test("supprime un message avec confirmation", async () => {
    mockApi();
    setup();
    await openFirstConversation();
    await waitFor(
      () => expect(screen.getByText("Hello everyone!")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.click(screen.getAllByText("🗑")[1]);
    expect(global.confirm).toHaveBeenCalledWith("Delete this message?");
    await waitFor(
      () =>
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining("/api/delete/message/1"),
          expect.objectContaining({ method: "DELETE" }),
        ),
      { timeout: 3000 },
    );
  });

  test("annule la suppression d'un message", async () => {
    global.confirm.mockReturnValueOnce(false);
    mockApi();
    setup();
    await openFirstConversation();
    await waitFor(
      () => expect(screen.getByText("Hello everyone!")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.click(screen.getAllByText("🗑")[1]);
    expect(global.fetch).not.toHaveBeenCalledWith(
      expect.stringContaining("/api/delete/message/"),
      expect.anything(),
    );
  });

  test.each([
    [
      "Friends (0)",
      "No friends available",
      { "/api/user/friends": [], "/api/get/conversations": [] },
    ],
    ["Conversations (0)", null, { "/api/get/conversations": [] }],
  ])("affiche liste vide : %s", async (text, extra, overrides) => {
    mockApi(overrides);
    setup();
    await waitFor(
      () => expect(screen.getByText(textMatch(text))).toBeInTheDocument(),
      { timeout: 3000 },
    );
    if (extra) expect(screen.getByText(extra)).toBeInTheDocument();
  });

  test.each([
    ["titre trop court", "A", /Title must be between 2 and 255 characters/i],
    [
      "titre trop long",
      "A".repeat(256),
      /Title must be between 2 and 255 characters/i,
    ],
  ])("validation : %s", async (_, value, error) => {
    mockApi();
    setup();
    await waitFor(
      () => expect(screen.getByText("New Conversation")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.change(screen.getByPlaceholderText("Title (2-255 characters)"), {
      target: { value },
    });
    fireEvent.click(screen.getAllByRole("checkbox")[0]);
    fireEvent.click(screen.getByText("Create"));
    await waitFor(() => expect(screen.getByText(error)).toBeInTheDocument(), {
      timeout: 3000,
    });
  });

  test("validation : message trop long", async () => {
    mockApi();
    setup();
    await openFirstConversation();
    await waitFor(
      () =>
        expect(
          screen.getByPlaceholderText(/Type your message/i),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
    fireEvent.change(screen.getByPlaceholderText(/Type your message/i), {
      target: { value: "A".repeat(251) },
    });
    fireEvent.click(screen.getByText("Send"));
    await waitFor(
      () =>
        expect(
          screen.getByText(/Message cannot exceed 250 characters/i),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });
  test.each([
    [
      "création de conversation",
      "/api/create/conversation",
      async () => {
        await waitFor(
          () =>
            expect(screen.getByText("New Conversation")).toBeInTheDocument(),
          { timeout: 3000 },
        );
        fireEvent.change(
          screen.getByPlaceholderText("Title (2-255 characters)"),
          { target: { value: "Test" } },
        );
        fireEvent.click(screen.getAllByRole("checkbox")[0]);
        fireEvent.click(screen.getByText("Create"));
      },
    ],
    [
      "envoi de message",
      "/api/create/message",
      async () => {
        await openFirstConversation();
        await waitFor(
          () =>
            expect(
              screen.getByPlaceholderText(/Type your message/i),
            ).toBeInTheDocument(),
          { timeout: 3000 },
        );
        fireEvent.change(screen.getByPlaceholderText(/Type your message/i), {
          target: { value: "Test!" },
        });
        fireEvent.click(screen.getByText("Send"));
      },
    ],
  ])("erreur réseau : %s", async (_, failUrl, act) => {
    mockApi({ [failUrl]: () => Promise.reject(new Error("Network error")) });
    setup();
    await act();
    // Vérifie que fetch a bien été appelé avec la bonne URL (l'erreur réseau a bien été déclenchée)
    await waitFor(
      () =>
        expect(global.fetch).toHaveBeenCalledWith(
          expect.stringContaining(failUrl),
          expect.anything(),
        ),
      { timeout: 3000 },
    );
  });
});
