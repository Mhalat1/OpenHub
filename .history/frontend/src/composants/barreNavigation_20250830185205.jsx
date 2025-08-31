import { Link } from "react-router-dom";

const Navbar = () => {
  return (
    <nav style={{ padding: "10px", background: "#f4f4f4" }}>
      <ul style={{ listStyle: "none", display: "flex", gap: "20px" }}>
        <li>
          <Link to="/">🏠 Dashboard</Link>
        </li>
        <li>
          <Link to="/profil">👤 Profil</Link>
        </li>
        <li>
          <Link to="/login">🔑 Connexion</Link>
        </li>
        <li>
          <Link to="/register">📝 Inscription</Link>
        </li>
      </ul>
    </nav>
  );
};

export default Navbar;
