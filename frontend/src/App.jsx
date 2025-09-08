import { Route, BrowserRouter as Router, Routes, useLocation } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Accueil from "./pages/Accueil";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Deconnexion from "./pages/deconnexion";
import Connexion from "./pages/connexion";
import Projets from "./pages/projets";
import UserCreate from "./pages/inscription";

function AppContent() {
  const location = useLocation();

  // Routes où la Navbar ne doit pas s'afficher
  const noNavbarPaths = ["/connexion", "/inscription", "/deconnexion"];

  // Condition pour afficher Navbar
  const showNavbar = !noNavbarPaths.includes(location.pathname);

  return (
    <>
      {showNavbar && <Navbar />}

      <Routes>
        <Route path="/accueil" element={<Accueil />} />
        <Route path="/profil" element={<Profil />} />
        <Route path="/projets" element={<Projets />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/deconnexion" element={<Deconnexion />} />
        <Route path="/connexion" element={<Connexion />} />
        <Route path="/inscription" element={<UserCreate />} />

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
