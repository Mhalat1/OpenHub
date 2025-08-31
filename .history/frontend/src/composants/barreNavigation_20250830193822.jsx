import { Link } from "react-router-dom";

const Navbar = () => {
  return (
    <nav>
      <ul>
        <li><Link to="/profil">🏠 Profil</Link></li>
        <li><Link to="/projets">👤 Projets</Link></li>
        <li><Link to="/messages">🔑 Messages</Link></li>
        <li><Link to="/deconnexion">📝 Deconnexion</Link></li>
        <li><Link to="/connexion">📝 Login</Link></li>
      </ul>
    </nav>
  );
};

export default Navbar;
