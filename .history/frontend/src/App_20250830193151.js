import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Deconnexion from "./pages/deconnexion";
import Projets from "./pages/Projets";

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/profil" element={<Profil />} />
        <Route path="/projets" element={<Projets />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/deconnexion" element={<Deconnexion />} />
      </Routes>
    </Router>
  );
}

export App