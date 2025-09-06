import { Link } from "react-router-dom";
import "../style/BarreNav.css"; 
import accueilicon from "../images/icones/accueil.png";
import profilicon from "../images/icones/profil.png";
import projetsicon from "../images/icones/projets.png";
import messageicon from "../images/icones/messages.png";
import deconnexionicon from "../images/icones/deconnexion.png";

const Navbar = () => {
  return (
    <nav>
      <ul>
        <li><Link to="/accueil"> <img src={accueilicon} alt="icon accueil" /> </Link></li>
        <li><Link to="/profil"> <img src={profilicon} alt="icon profil" /> </Link></li>
        <li><Link to="/projets"> <img src={projetsicon} alt="icon projet" /> </Link></li>
        <li><Link to="/messages"> <img src={messageicon} alt="icon messages" /> </Link></li>
        <li><Link to="/deconnexion"> <img src={deconnexionicon} alt="icon deconnexion" /> </Link></li>
      </ul>
    </nav>
  );
};

export default Navbar;
