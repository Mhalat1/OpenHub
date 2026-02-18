import { render, screen } from "@testing-library/react";
import { BrowserRouter } from "react-router-dom";
import App from "./App";
import "@testing-library/jest-dom";

test("renders app with router", () => {
  render(
    <BrowserRouter>
      <App />
    </BrowserRouter>,
  );

  // Just check that the app renders without crashing
  // You can add more specific assertions based on your App's content
  expect(document.body).toBeInTheDocument();
});
