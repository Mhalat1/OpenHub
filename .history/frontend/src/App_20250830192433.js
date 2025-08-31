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
        <Route path="/projet" element={<Dashboard />} />
        <Route path="/messages" element={<Login />} />
        <Route path="/register" element={<Register />} />
      </Routes>
    </Router>
  );
}

