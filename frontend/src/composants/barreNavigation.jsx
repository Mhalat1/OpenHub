import { Link } from "react-router-dom";
import "../style/BarreNav.css"; 

const Navbar = () => {
  return (
    <nav>
      <ul>
        <li><Link to="/profil">🏠</Link></li>
        <li><Link to="/projets">👤</Link></li>
        <li><Link to="/messages">🔑 </Link></li>
        <li><Link to="/deconnexion">📝 </Link></li>
        <li><Link to="/connexion">📝 </Link></li>
        <li><Link to="/inscription">🔑 </Link></li>
      </ul>
    </nav>
  );
};

export default Navbar;
