import "@testing-library/jest-dom";
import { fireEvent, render, screen } from "@testing-library/react";
import LogoutButton from "../pages/Logout";

const localStorageMock = {
  getItem: jest.fn(),
  setItem: jest.fn(),
  removeItem: jest.fn(),
  clear: jest.fn(),
};

beforeAll(() => {
  delete window.location;
  window.location = { href: "http://localhost/" };
  Object.defineProperty(window, "localStorage", {
    value: localStorageMock,
    writable: true,
  });
});

beforeEach(() => {
  jest.clearAllMocks();
  jest.useFakeTimers();
  window.location.href = "http://localhost/";
});

const setup = () => render(<LogoutButton />);
const getBtn = (name) => screen.getByRole("button", { name });

describe("LogoutButton", () => {
  it("affiche le titre et les deux boutons actifs", () => {
    setup();
    expect(screen.getByText("See you soon 👋")).toBeInTheDocument();
    expect(getBtn(/logout/i)).not.toBeDisabled();
    expect(getBtn(/return to home/i)).not.toBeDisabled();
  });

  it("a la bonne structure DOM", () => {
    const { container } = setup();
    const actions = container.querySelector(".logout-actions");
    expect(actions.querySelectorAll("button")).toHaveLength(2);
    expect(container.querySelectorAll("p")).toHaveLength(2);
    expect(container.querySelector("h1")).toHaveClass("logout-title");
  });

  it("logout : supprime le token et crée un timer", () => {
    setup();
    fireEvent.click(getBtn(/logout/i));
    expect(localStorageMock.removeItem).toHaveBeenCalledWith("token");
    expect(jest.getTimerCount()).toBe(1);
  });

  it("return home : ne touche pas au token ni aux timers", () => {
    setup();
    fireEvent.click(getBtn(/return to home/i));
    expect(localStorageMock.removeItem).not.toHaveBeenCalled();
    expect(jest.getTimerCount()).toBe(0);
  });
});
