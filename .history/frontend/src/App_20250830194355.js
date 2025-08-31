import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Messages from "./pages/Messages";
import Profil from "./pages/Profil";
import Deconnexion from "./pages/deconnexion";
import Login from "./pages/connexion";
import Projets from "./pages/projets";

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/profil" element={<Profil />} />
        <Route path="/projets" element={<Projets />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/deconnexion" element={<Deconnexion />} />
        <Route path="/connexion" element={<Login />} />
      </Routes>
    </Router>
  );
}

export default App;