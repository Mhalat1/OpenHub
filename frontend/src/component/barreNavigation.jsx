import { Link } from "react-router-dom";
import "../style/BarreNav.css"; 
import homeicon from "../images/icones/home.png";
import profilicon from "../images/icones/profil.png";
import Projectsicon from "../images/icones/projects.png";
import messageicon from "../images/icones/messages.png";
import logouticon from "../images/icones/logout.png";

const Navbar = () => {
  return (
    <nav>
      <ul>
        <li><Link to="/home"> <img src={homeicon} alt="icon home" /> </Link></li>
        <li><Link to="/profil"> <img src={profilicon} alt="icon profil" /> </Link></li>
        <li><Link to="/Projects"> <img src={Projectsicon} alt="icon projet" /> </Link></li>
        <li><Link to="/messages"> <img src={messageicon} alt="icon messages" /> </Link></li>
        <li><Link to="/logout"> <img src={logouticon} alt="icon logout" /> </Link></li>
      </ul>
    </nav>
  );
};

export default Navbar;
