import { Link, useLocation } from "react-router-dom";
import homeicon from "../images/icones/home.png";
import logouticon from "../images/icones/logout.png";
import messageicon from "../images/icones/messages.png";
import profilicon from "../images/icones/profil.png";
import Projectsicon from "../images/icones/projects.png";
import "../style/BarreNav.css";

const Navbar = () => {
  const location = useLocation();

  return (
    <nav className="navbar">
      <ul>
        <li className={location.pathname === "/home" ? "active" : ""}>
          <Link to="/home" title="Accueil">
            <img src={homeicon} alt="Accueil" />
            <span className="nav-label">Accueil</span>
          </Link>
        </li>
        <li className={location.pathname === "/profil" ? "active" : ""}>
          <Link to="/profil" title="Profil">
            <img src={profilicon} alt="Profil" />
            <span className="nav-label">Profil</span>
          </Link>
        </li>
        <li className={location.pathname === "/projects" ? "active" : ""}>
          <Link to="/projects" title="Projets">
            <img src={Projectsicon} alt="Projets" />
            <span className="nav-label">Projets</span>
          </Link>
        </li>
        <li className={location.pathname === "/messages" ? "active" : ""}>
          <Link to="/messages" title="Messages">
            <img src={messageicon} alt="Messages" />
            <span className="nav-label">Messages</span>
          </Link>
        </li>
        <li className={location.pathname === "/logout" ? "active" : ""}>
          <Link to="/logout" title="logout">
            <img src={logouticon} alt="logout" />
            <span className="nav-label">logout</span>
          </Link>
        </li>
      </ul>
    </nav>
  );
};

export default Navbar;
