import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Login from "./pages/connexion";
import profil from "./pages/Profil";
import Register from "./pages/inscription"

function App() {
  return (
    <Router>
      <Navbar />
      <Routes>
        <Route path="/" element={<profil />} />
        <Route path="/profil" element={<UserProfile />} />
        <Route path="/connexion" element={<Login />} />
        <Route path="/inscription" element={<Register />} />
      </Routes>
    </Router>
  );
}

export default App;
