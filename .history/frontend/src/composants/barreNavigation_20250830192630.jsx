import { Link } from "react-router-dom";

const Navbar = () => {
  return (
    <nav>
      <ul>
        <li><Link to="/">🏠 profil</Link></li>
        <li><Link to="/profil">👤 Profil</Link></li>
        <li><Link to="/login">🔑 Connexion</Link></li>
        <li><Link to="/deconnexion">📝 Inscription</Link></li>
      </ul>
    </nav>
  );
};

export default Navbar;
