import React from "react";
import { render, screen, fireEvent } from "@testing-library/react";
import LogoutButton from "../pages/Logout";

// Mock du CSS
jest.mock("../style/logout.css", () => ({}));

describe("LogoutButton Component", () => {
  // Mock de window.location
  let originalLocation;

  // Mock de localStorage avec jest.fn()
  const localStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn(),
  };

  beforeAll(() => {
    originalLocation = window.location;
    delete window.location;
    window.location = { href: "http://localhost/" };

    // Remplacer localStorage par le mock
    Object.defineProperty(window, "localStorage", {
      value: localStorageMock,
      writable: true,
    });
  });

  afterAll(() => {
    window.location = originalLocation;
  });

  beforeEach(() => {
    jest.clearAllMocks();
    window.location.href = "http://localhost/";
    localStorageMock.getItem.mockClear();
    localStorageMock.setItem.mockClear();
    localStorageMock.removeItem.mockClear();
    localStorageMock.clear.mockClear();
    jest.useFakeTimers();
  });

  afterEach(() => {
    jest.runOnlyPendingTimers();
    jest.useRealTimers();
  });

  describe("Rendering", () => {
    it("should render the logout card with title", () => {
      render(<LogoutButton />);
      expect(screen.getByText("See you soon 👋")).toBeInTheDocument();
    });

    it("should render both action buttons", () => {
      render(<LogoutButton />);
      expect(
        screen.getByRole("button", { name: /logout/i }),
      ).toBeInTheDocument();
      expect(
        screen.getByRole("button", { name: /return to home/i }),
      ).toBeInTheDocument();
    });

    it("should have correct CSS classes", () => {
      const { container } = render(<LogoutButton />);
      expect(container.querySelector(".logout-container")).toBeInTheDocument();
      expect(container.querySelector(".logout-card")).toBeInTheDocument();
      expect(container.querySelector(".logout-title")).toBeInTheDocument();
      expect(container.querySelector(".logout-subtitle")).toBeInTheDocument();
      expect(container.querySelector(".logout-text")).toBeInTheDocument();
      expect(container.querySelector(".logout-actions")).toBeInTheDocument();
    });

    it("should render logout button with correct class", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      expect(logoutButton).toHaveClass("logout-btn");
    });

    it("should render return home button with correct class", () => {
      render(<LogoutButton />);
      const returnHomeButton = screen.getByRole("button", {
        name: /return to home/i,
      });
      expect(returnHomeButton).toHaveClass("return-btn");
    });

    it("should display the wave emoji in title", () => {
      render(<LogoutButton />);
      const title = screen.getByText("See you soon 👋");
      expect(title).toHaveTextContent("👋");
    });
  });

  describe("Logout Functionality", () => {
    it("should remove token from localStorage when logout button is clicked", () => {
      localStorageMock.setItem("token", "fake-jwt-token");
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      fireEvent.click(logoutButton);
      expect(localStorageMock.removeItem).toHaveBeenCalledWith("token");
      expect(localStorageMock.removeItem).toHaveBeenCalledTimes(1);
    });

    it("should set a timeout when logout button is clicked", () => {
      const setTimeoutSpy = jest.spyOn(global, "setTimeout");
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      fireEvent.click(logoutButton);
      expect(setTimeoutSpy).toHaveBeenCalledWith(expect.any(Function), 2000);
    });

    it("should call localStorage.removeItem multiple times if clicked multiple times", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      fireEvent.click(logoutButton);
      fireEvent.click(logoutButton);
      fireEvent.click(logoutButton);
      expect(localStorageMock.removeItem).toHaveBeenCalledTimes(3);
    });

    it("should handle logout when token does not exist", () => {
      localStorageMock.clear();
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      expect(() => fireEvent.click(logoutButton)).not.toThrow();
      expect(localStorageMock.removeItem).toHaveBeenCalledWith("token");
    });
  });

  describe("Return Home Functionality", () => {
    it("should not remove token when return home button is clicked", () => {
      localStorageMock.setItem("token", "fake-jwt-token");
      render(<LogoutButton />);
      const returnHomeButton = screen.getByRole("button", {
        name: /return to home/i,
      });
      fireEvent.click(returnHomeButton);
      expect(localStorageMock.removeItem).not.toHaveBeenCalled();
    });

    it("should preserve token in localStorage when returning home", () => {
      localStorageMock.setItem("token", "fake-jwt-token");
      render(<LogoutButton />);
      const returnHomeButton = screen.getByRole("button", {
        name: /return to home/i,
      });
      fireEvent.click(returnHomeButton);
      expect(localStorageMock.removeItem).not.toHaveBeenCalled();
    });
  });

  describe("Button Interactions", () => {
    it("should have clickable logout button", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      expect(logoutButton).not.toBeDisabled();
      fireEvent.click(logoutButton);
      expect(localStorageMock.removeItem).toHaveBeenCalled();
    });

    it("should have both buttons enabled by default", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      const returnHomeButton = screen.getByRole("button", {
        name: /return to home/i,
      });
      expect(logoutButton).not.toBeDisabled();
      expect(returnHomeButton).not.toBeDisabled();
    });
  });

  describe("Component Structure", () => {
    it("should have logout-actions div containing both buttons", () => {
      const { container } = render(<LogoutButton />);
      const actionsDiv = container.querySelector(".logout-actions");
      const buttons = actionsDiv.querySelectorAll("button");
      expect(buttons).toHaveLength(2);
    });

    it("should render logout button before return home button", () => {
      const { container } = render(<LogoutButton />);
      const actionsDiv = container.querySelector(".logout-actions");
      const buttons = actionsDiv.querySelectorAll("button");
      expect(buttons[0]).toHaveTextContent("Logout");
      expect(buttons[1]).toHaveTextContent("Return to Home");
    });

    it("should have exactly one h1 element", () => {
      const { container } = render(<LogoutButton />);
      const h1Elements = container.querySelectorAll("h1");
      expect(h1Elements).toHaveLength(1);
    });

    it("should have exactly two paragraph elements", () => {
      const { container } = render(<LogoutButton />);
      const paragraphs = container.querySelectorAll("p");
      expect(paragraphs).toHaveLength(2);
    });

    it("should have exactly two buttons", () => {
      const { container } = render(<LogoutButton />);
      const buttons = container.querySelectorAll("button");
      expect(buttons).toHaveLength(2);
    });

    it("should have correct DOM hierarchy", () => {
      const { container } = render(<LogoutButton />);
      const logoutContainer = container.querySelector(".logout-container");
      const logoutCard = logoutContainer.querySelector(".logout-card");
      const logoutActions = logoutCard.querySelector(".logout-actions");
      expect(logoutContainer).toBeInTheDocument();
      expect(logoutCard).toBeInTheDocument();
      expect(logoutActions).toBeInTheDocument();
    });
  });

  describe("Edge Cases", () => {});

  describe("Accessibility", () => {
    it("should have accessible button names", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      const returnHomeButton = screen.getByRole("button", {
        name: /return to home/i,
      });
      expect(logoutButton).toHaveAccessibleName("Logout");
      expect(returnHomeButton).toHaveAccessibleName("Return to Home");
    });

    it("should have proper heading hierarchy", () => {
      const { container } = render(<LogoutButton />);
      const h1 = container.querySelector("h1");
      expect(h1).toBeInTheDocument();
      expect(h1).toHaveClass("logout-title");
    });
  });

  describe("Timer Management", () => {
    it("should create only one timer per logout click", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      fireEvent.click(logoutButton);
      expect(jest.getTimerCount()).toBe(1);
    });

    it("should create multiple timers if logout is clicked multiple times", () => {
      render(<LogoutButton />);
      const logoutButton = screen.getByRole("button", { name: /logout/i });
      fireEvent.click(logoutButton);
      fireEvent.click(logoutButton);
      fireEvent.click(logoutButton);
      expect(jest.getTimerCount()).toBe(3);
    });

    it("should not create timers when return home is clicked", () => {
      render(<LogoutButton />);
      const returnHomeButton = screen.getByRole("button", {
        name: /return to home/i,
      });
      fireEvent.click(returnHomeButton);
      expect(jest.getTimerCount()).toBe(0);
    });
  });
});
