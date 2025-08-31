import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Deconnexion from "./pages/deconnexion";
import Projets from "./pages/projets";

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/Profil" element={<Profil />} />
        <Route path="/Projets" element={<Projets />} />
        <Route path="/Messages" element={<Messages />} />
        <Route path="/deconnexion" element={<Deconnexion />} />
      </Routes>
    </Router>
  );
}

