import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Projets from "./pages/projets";
import Deconnexion from "./pages/deconnexion";

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/profil" element={<Profil />} />
        <Route path="/projet" element={<Projet />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/deconnexion" element={<Deconnexion />} />
      </Routes>
    </Router>
  );
}

