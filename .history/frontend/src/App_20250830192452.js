import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Login from "./pages/Messages";
import UserProfile from "./pages/Profil";
import Register from "./pages/deconnexion";
import Dashboard from "./pages/accueil";

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/profil" element={<UserProfile />} />
        <Route path="/projet" element={<Projet />} />
        <Route path="/messages" element={<Messages />} />
        <Route path="/deconnexion" element={<Register />} />
      </Routes>
    </Router>
  );
}

