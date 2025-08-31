import { Route, BrowserRouter as Router, Routes } from "react-router-dom";
import Navbar from "./composants/barreNavigation";
import Login from "./pages/connexion";
import UserProfile from "./pages/Profil";
import Register from "./pages/inscription";
import Dashboard from "./pages/accueil";

function App() {
  return (
    <Router>
      <Navbar />
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

const UserProfile = () => {
  return <h1>Page de connexion</h1>;
};

export default UserProfile;

export default App;

const Login = () => {
  return <h1>Page de connexion</h1>;
};

export default Login;

export default App;

const UserProfile = () => {
  return <h1>Page de connexion</h1>;
};

export default UserProfile;
