import { Link } from "react-router-dom";

const Navbar = () => {
  return (
    <nav>
      <ul>
        <li><Link to="/profil">🏠 profil</Link></li>
        <li><Link to="/projets">👤 Projets</Link></li>
        <li><Link to="/messages">🔑 Connexion</Link></li>
        <li><Link to="/deconnexion">📝 Deconnexion</Link></li>
      </ul>
    </nav>
  );
};

export default Navbar;
