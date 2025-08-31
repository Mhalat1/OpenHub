import { BrowserRouter as Router, Routes, Route } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import UserProfile from "./pages/Profil";
import Login from "./pages/Login";
import Register from "./pages/Register";

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
