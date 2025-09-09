import { Route, BrowserRouter as Router, Routes, useLocation } from "react-router-dom";
import Navbar from "./component/barreNavigation";
import Home from "./pages/Home";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Logout from "./pages/logout";
import Login from "./pages/Login";
import Projects from "./pages/Projects";
import UserCreate from "./pages/Register";

function AppContent() {
  const location = useLocation();

  // Routes où la Navbar ne doit pas s'afficher
  const noNavbarPaths = ["/login", "/register", "/logout"];

  // Condition pour afficher Navbar
  const showNavbar = !noNavbarPaths.includes(location.pathname);

  return (
    <>
      {showNavbar && <Navbar />}

      <Routes>
        <Route path="/home" element={<Home />} />
        <Route path="/profil" element={<Profil />} />
        <Route path="/projects" element={<Projects />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/logout" element={<Logout />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<UserCreate />} />

      </Routes>
    </>
  );
}

function App() {
  return (
    <Router>
      <AppContent />
    </Router>
  );
}

export default App;
