import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Login from "./pages/connexion";
import UserProfile from "./pages/Profil";
import Register from "./pages/inscription";
import Dashboard

function App() {
  return (
    <Router>
      <Navbar />  {/* Navbar au-dessus de toutes les pages */}
      <Routes>
        <Route path="/" element={<Dashboard />} />
        <Route path="/profil" element={<UserProfile />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
      </Routes>
    </Router>
  );
}

export default App;
