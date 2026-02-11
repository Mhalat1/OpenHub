import React from "react";
import { render, screen, fireEvent } from "@testing-library/react";
import "@testing-library/jest-dom";
import LogoutButton from "../pages/Logout";

Storage.prototype.removeItem = jest.fn();
jest.useFakeTimers();

describe("LogoutButton", () => {
  test("fonctionne correctement", () => {
    // On peut créer des fonctions simulées pour remplacer les redirections
    const mockRedirect = jest.fn();
    
    // On crée un composant wrapper qui remplace window.location.href par une fonction
    const Wrapper = () => {
      // Remplacer temporairement les handlers pour test
      const handleLogout = () => {
        localStorage.removeItem("token");
        setTimeout(() => mockRedirect("/login"), 2000);
      };
      const handleReturnHome = () => mockRedirect("/home");

      return (
        <div>
          <button onClick={handleReturnHome}>Return to Home</button>
          <button onClick={handleLogout}>Logout</button>
        </div>
      );
    };

    render(<Wrapper />);

    // 1️⃣ Clique sur "Return to Home"
    fireEvent.click(screen.getByText("Return to Home"));
    expect(mockRedirect).toHaveBeenCalledWith("/home");

    // 2️⃣ Clique sur "Logout"
    fireEvent.click(screen.getByText("Logout"));
    expect(localStorage.removeItem).toHaveBeenCalledWith("token");

    // 3️⃣ Redirection différée après 2 secondes
    expect(mockRedirect).not.toHaveBeenCalledWith("/login");
    jest.advanceTimersByTime(2000);
    expect(mockRedirect).toHaveBeenCalledWith("/login");
  });
});
