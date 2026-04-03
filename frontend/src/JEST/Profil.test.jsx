import "@testing-library/jest-dom";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { BrowserRouter } from "react-router-dom";

const ProfilModule = require("../pages/Profil");
const Profil = ProfilModule.default || ProfilModule.Profil || ProfilModule;

global.fetch = jest.fn();
window.confirm = jest.fn(() => true);
Object.defineProperty(window, "localStorage", {
  value: {
    getItem: jest.fn(() => "mock-token"),
    setItem: jest.fn(),
    clear: jest.fn(),
    removeItem: jest.fn(),
  },
});

beforeEach(() => jest.clearAllMocks());

const mockConnectedUser = {
  id: 4,
  firstName: "John",
  lastName: "Doe",
  email: "john@example.com",
};
const mockUsers = [
  { id: 1, firstName: "Alice", lastName: "Smith", email: "alice@example.com" },
  { id: 2, firstName: "Bob", lastName: "Johnson", email: "bob@example.com" },
  {
    id: 3,
    firstName: "Charlie",
    lastName: "Brown",
    email: "charlie@example.com",
  },
];
const mockFriends = [
  { id: 5, firstName: "David", lastName: "Wilson", email: "david@example.com" },
];
const mockSentInvitations = [
  {
    id: 1,
    recipient_id: 1,
    firstName: "Emma",
    lastName: "Watson",
    email: "emma@example.com",
    status: "pending",
  },
];
const mockReceivedInvitations = [
  {
    id: 2,
    sender_id: 6,
    firstName: "Frank",
    lastName: "Miller",
    email: "frank@example.com",
    status: "pending",
  },
];

const mockApi = (overrides = {}) => {
  const defaults = {
    getConnectedUser: mockConnectedUser,
    getAllUsers: mockUsers,
    friends: mockFriends,
    "invitations/sent": mockSentInvitations,
    "invitations/received": mockReceivedInvitations,
  };
  fetch.mockImplementation((url) => {
    const responses = { ...defaults, ...overrides };
    for (const [key, data] of Object.entries(responses)) {
      if (url.includes(key))
        return Promise.resolve({ ok: true, json: () => Promise.resolve(data) });
    }
    return Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    });
  });
};

const setup = async (overrides = {}) => {
  mockApi(overrides);
  render(
    <BrowserRouter>
      <Profil />
    </BrowserRouter>,
  );
  const user = userEvent.setup();
  await waitFor(
    () => expect(screen.getByText("Alice Smith")).toBeInTheDocument(),
    { timeout: 3000 },
  );
  return user;
};

const setupEmpty = async () => {
  mockApi({
    getAllUsers: [mockConnectedUser],
    friends: [],
    "invitations/sent": [],
    "invitations/received": [],
  });
  render(
    <BrowserRouter>
      <Profil />
    </BrowserRouter>,
  );
  const user = userEvent.setup();
  await waitFor(
    () =>
      expect(screen.getByText("Aucun utilisateur trouvé")).toBeInTheDocument(),
    { timeout: 3000 },
  );
  return user;
};

const clickTab = async (user, name) => {
  const tab = screen.getByRole("button", { name });
  await user.click(tab);
  return tab;
};

describe("Profil Component", () => {
  test("affiche le chargement initial", () => {
    fetch.mockImplementation(() => new Promise(() => {}));
    render(
      <BrowserRouter>
        <Profil />
      </BrowserRouter>,
    );
    expect(screen.getByText("Chargement des données...")).toBeInTheDocument();
  });

  test("affiche les utilisateurs et les compteurs d'onglets", async () => {
    await setup();
    expect(screen.getByText("Réseau Social")).toBeInTheDocument();
    expect(screen.getByText("Charlie Brown")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /👥 Amis \(1\)/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /📤 Invitations Envoyées \(1\)/i }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: /📥 Invitations Reçues \(1\)/i }),
    ).toBeInTheDocument();
  });

  test("filtre par recherche", async () => {
    const user = await setup({
      friends: [],
      "invitations/sent": [],
      "invitations/received": [],
    });
    await user.type(
      screen.getByPlaceholderText("Filtrer les utilisateurs..."),
      "Alice",
    );
    await waitFor(
      () => expect(screen.queryByText("Bob Johnson")).not.toBeInTheDocument(),
      { timeout: 3000 },
    );
    expect(screen.getByText("Alice Smith")).toBeInTheDocument();
  });

  test("onglet Amis → affiche les amis", async () => {
    const user = await setup();
    const tab = await clickTab(user, /👥 Amis \(1\)/i);
    expect(tab).toHaveClass("active");
    await waitFor(
      () => expect(screen.getByText("David Wilson")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    expect(screen.getByText("👥 Mes Amis")).toBeInTheDocument();
  });

  test("onglet Invitations Envoyées → affiche Emma Watson", async () => {
    const user = await setup();
    const tab = await clickTab(user, /📤 Invitations Envoyées \(1\)/i);
    expect(tab).toHaveClass("active");
    await waitFor(
      () => expect(screen.getByText("Emma Watson")).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("onglet Invitations Reçues → affiche Frank Miller", async () => {
    const user = await setup();
    const tab = await clickTab(user, /📥 Invitations Reçues \(1\)/i);
    expect(tab).toHaveClass("active");
    await waitFor(
      () => expect(screen.getByText("Frank Miller")).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("états vides sur tous les onglets", async () => {
    const user = await setupEmpty();
    await clickTab(user, /👥 Amis \(0\)/i);
    await waitFor(
      () =>
        expect(
          screen.getByText("Aucun ami pour le moment"),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
    await clickTab(user, /📤 Invitations Envoyées \(0\)/i);
    await waitFor(
      () =>
        expect(
          screen.getByText("Aucune invitation envoyée"),
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
    await clickTab(user, /📥 Invitations Reçues \(0\)/i);
    await waitFor(
      () =>
        expect(screen.getByText("Aucune invitation reçue")).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("ouvre le modal utilisateur", async () => {
    const user = await setup({
      friends: [],
      "invitations/sent": [],
      "invitations/received": [],
    });
    await user.click(screen.getAllByText("+")[0]);
    await waitFor(
      () =>
        expect(screen.getByText("➕ Ajouter comme ami")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    expect(screen.getByText(/📧 Email:/i)).toBeInTheDocument();
  });

  test("supprime un ami", async () => {
    const user = await setup({
      "invitations/sent": [],
      "invitations/received": [],
    });
    await clickTab(user, /👥 Amis \(1\)/i);
    await waitFor(
      () => expect(screen.getByText("David Wilson")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    await user.click(screen.getByText("❌ Supprimer"));
    await waitFor(
      () =>
        expect(fetch).toHaveBeenCalledWith(
          expect.stringContaining("delete/friends/5"),
          expect.objectContaining({ method: "DELETE" }),
        ),
      { timeout: 3000 },
    );
  });

  test("envoie une invitation depuis le modal", async () => {
    const user = await setup({
      friends: [],
      "invitations/sent": [],
      "invitations/received": [],
    });
    await user.click(screen.getAllByText("+")[0]);
    await waitFor(
      () =>
        expect(screen.getByText("➕ Ajouter comme ami")).toBeInTheDocument(),
      { timeout: 3000 },
    );
    await user.click(screen.getByText("➕ Ajouter comme ami"));
    await waitFor(
      () =>
        expect(fetch).toHaveBeenCalledWith(
          expect.stringContaining("send/invitation"),
          expect.objectContaining({
            method: "POST",
            body: JSON.stringify({ friend_id: 1 }),
          }),
        ),
      { timeout: 3000 },
    );
  });

  test.each([
    [
      "accepte une invitation reçue",
      /📥 Invitations Reçues \(1\)/i,
      "Frank Miller",
      "Accepter",
      "invitations/accept/2",
      "POST",
    ],
    [
      "refuse une invitation reçue",
      /📥 Invitations Reçues \(1\)/i,
      "Frank Miller",
      "Refuser",
      "delete-received/2",
      "DELETE",
    ],
    [
      "annule une invitation envoyée",
      /📤 Invitations Envoyées \(1\)/i,
      "Emma Watson",
      "Annuler",
      "delete-sent/1",
      "DELETE",
    ],
  ])("%s", async (_, tabName, person, btnText, endpoint, method) => {
    const user = await setup({ friends: [] });
    await clickTab(user, tabName);
    await waitFor(() => expect(screen.getByText(person)).toBeInTheDocument(), {
      timeout: 3000,
    });
    await user.click(screen.getByText(btnText));
    await waitFor(
      () =>
        expect(fetch).toHaveBeenCalledWith(
          expect.stringContaining(endpoint),
          expect.objectContaining({ method }),
        ),
      { timeout: 3000 },
    );
  });
});
