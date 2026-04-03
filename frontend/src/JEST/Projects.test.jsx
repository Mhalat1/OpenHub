import "@testing-library/jest-dom";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import Projects from "../pages/Projects";

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

const mockProjects = [
  {
    id: 1,
    name: "Project Alpha",
    description: "A cutting-edge web application",
    requiredSkills: "React, Node.js, MongoDB",
    startDate: "2024-01-01T00:00:00.000Z",
    endDate: "2024-06-30T00:00:00.000Z",
  },
  {
    id: 2,
    name: "Project Beta",
    description: "Mobile app development",
    requiredSkills: "React Native, Firebase",
    startDate: "2024-02-01T00:00:00.000Z",
    endDate: "2024-08-31T00:00:00.000Z",
  },
];
const mockSkills = [
  {
    id: 1,
    name: "Frontend Development",
    description: "UI building",
    technoUtilisees: "React, Vue",
    duree: "3 months",
  },
  {
    id: 2,
    name: "Backend Development",
    description: "Server side",
    technoUtilisees: "Node.js",
    duree: "4 months",
  },
];

// Correction : ordre des routes important — plus spécifique d'abord
const mockApi = () =>
  fetch.mockImplementation((url) => {
    if (url.includes("allprojects"))
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockProjects),
      });
    if (url.includes("skills/create"))
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true }),
      });
    if (url.includes("skills/update"))
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true }),
      });
    if (url.includes("skills/delete"))
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ success: true }),
      });
    if (url.includes("skills"))
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve(mockSkills),
      });
    return Promise.resolve({
      ok: true,
      json: () => Promise.resolve({ success: true }),
    });
  });

const setup = async () => {
  mockApi();
  render(<Projects />);
  const user = userEvent.setup();
  await waitFor(
    () => expect(screen.getAllByText("Project Alpha")[0]).toBeInTheDocument(),
    { timeout: 3000 },
  );
  await waitFor(
    () =>
      expect(
        screen.getAllByText("Frontend Development")[0],
      ).toBeInTheDocument(),
    { timeout: 3000 },
  );
  return user;
};

const expectFetch = (endpoint, method) =>
  waitFor(
    () =>
      expect(fetch).toHaveBeenCalledWith(
        expect.stringContaining(endpoint),
        expect.objectContaining({ method }),
      ),
    { timeout: 3000 },
  );

describe("Projects Component", () => {
  test("chargement initial", () => {
    fetch.mockImplementation(() => new Promise(() => {}));
    render(<Projects />);
    expect(
      screen.getAllByText(/loading projects and skills/i)[0],
    ).toBeInTheDocument();
  });

  test("affiche projets et skills", async () => {
    await setup();
    expect(
      screen.getAllByText(/Projects Management \(2\)/i)[0],
    ).toBeInTheDocument();
    expect(
      screen.getAllByText(/Skills Management \(2\)/i)[0],
    ).toBeInTheDocument();
  });

  test("filtre par recherche", async () => {
    const user = await setup();
    await user.type(
      screen.getByPlaceholderText(/search projects or skills/i),
      "React",
    );
    await waitFor(
      () =>
        expect(
          screen.queryByText("Backend Development"),
        ).not.toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("états vides", async () => {
    fetch.mockResolvedValue({ ok: true, json: () => Promise.resolve([]) });
    render(<Projects />);
    await waitFor(
      () =>
        expect(screen.getAllByText("No projects available")).toHaveLength(1),
      { timeout: 3000 },
    );
  });

  test("ferme la modale via overlay", async () => {
    const user = await setup();
    await user.click(screen.getAllByText(/✨ Create New Project/i)[0]);
    await user.click(document.querySelector(".modalOverlay"));
    await waitFor(
      () =>
        expect(
          screen.queryByText("Create New Project"),
        ).not.toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("validation champs requis", async () => {
    const user = await setup();
    await user.click(screen.getAllByText(/✨ Create New Project/i)[0]);
    await user.click(screen.getAllByText("✨ Create Project")[0]);
    await waitFor(
      () =>
        expect(
          screen.getAllByText(/name and description are required/i)[0],
        ).toBeInTheDocument(),
      { timeout: 3000 },
    );
  });

  test("crée un projet", async () => {
    const user = await setup();
    await user.click(screen.getAllByText(/✨ Create New Project/i)[0]);
    await user.type(
      screen.getByPlaceholderText("Enter project name"),
      "New Project",
    );
    await user.type(
      screen.getByPlaceholderText("Describe the project..."),
      "Description",
    );
    await user.click(screen.getAllByText("✨ Create Project")[0]);
    await expectFetch("create/new/project", "POST");
  });

  test("crée un skill", async () => {
    const user = await setup();
    await user.click(screen.getAllByText(/✨ Create New Skill/i)[0]);
    await user.type(
      screen.getByPlaceholderText("Enter skill name"),
      "New Skill",
    );
    await user.type(
      screen.getByPlaceholderText("Describe the skill..."),
      "Description",
    );
    await user.type(screen.getByPlaceholderText(/React, JavaScript/i), "React");
    await user.click(screen.getAllByText("✨ Create Skill")[0]);
    await expectFetch("skills/create", "POST");
  });

  test.each([
    [
      "projet",
      "Edit project",
      "Project Alpha",
      "💾 Update Project",
      "modify/project/1",
      "PUT",
    ],
    [
      "skill",
      "Edit skill",
      "Frontend Development",
      "💾 Update Skill",
      "skills/update/1",
      "PUT",
    ],
  ])(
    "modifie un %s",
    async (_, title, currentValue, submitText, endpoint, method) => {
      const user = await setup();
      await user.click(screen.getAllByTitle(title)[0]);
      const input = await screen.findByDisplayValue(currentValue);
      await user.clear(input);
      await user.type(input, "Updated");
      await user.click(screen.getAllByText(submitText)[0]);
      await expectFetch(endpoint, method);
    },
  );

  test.each([
    ["projet", "Delete project", "delete/project/1"],
    ["skill", "Delete skill", "skills/delete/1"],
  ])("supprime un %s", async (_, title, endpoint) => {
    const user = await setup();
    await user.click(screen.getAllByTitle(title)[0]);
    await expectFetch(endpoint, "DELETE");
  });
});
