import { Route, Routes, useLocation } from "react-router-dom";
import Navbar from "./component/barreNavigation";
import DonatePage from "./pages/DonatePage";
import Home from "./pages/Home";
import Login from "./pages/Login";
import Logout from "./pages/Logout";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Projects from "./pages/Projects";
import UserCreate from "./pages/Register";

function AppContent() {
  const location = useLocation();

  const noNavbarPaths = ["/login", "/register", "/logout"];
  const showNavbar = !noNavbarPaths.includes(location.pathname);

  return (
    <>
      {showNavbar && <Navbar />}
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/home" element={<Home />} />
        <Route path="/profil" element={<Profil />} />
        <Route path="/projects" element={<Projects />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/logout" element={<Logout />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<UserCreate />} />
        <Route path="/donate" element={<DonatePage />} />
        <Route path="*" element={<div>Page non trouvée - 404</div>} />
      </Routes>
    </>
  );
}

function App() {
  // ✅ Pas de BrowserRouter ici, il est dans main.jsx
  return <AppContent />;
}

export default App;