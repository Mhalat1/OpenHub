import { useState } from "react";
import { useNavigate } from "react-router-dom";
import "../style/inscription.css";
import logo from '../images/logo.png';

const UserCreate = () => {
  const [prenom, setPrenom] = useState("");
  const [nom, setNom] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [debutDispo, setDebutDispo] = useState("");
  const [finDispo, setFinDispo] = useState("");

  const [message, setMessage] = useState("");
  const [error, setError] = useState("");

  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setMessage("");
    setError("");
    

    try {
      const response = await fetch("http://127.0.0.1:8000/api/userCreate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ 
          email: email, 
          password: password, 
          prenom: prenom,
          nom: nom,
          debutDispo: debutDispo,
          finDispo: finDispo
        }),
      });

      const data = await response.json();

      if (data.status) {
        setMessage(data.message);
        setEmail("");
        setPassword("");
        setPrenom("");
        setNom("");
        setDebutDispo("");
        setFinDispo("");
        window.location.href = "/connexion"; // ou "/profil" si déjà connecté
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError("Erreur lors de la création de l'utilisateur");
      console.error(err);
    }
  };

  return (
    <div className="inscription-container">
      <h2>Créer un utilisateur</h2>
      <img src={logo} alt="logo" />
      <form onSubmit={handleSubmit}>
        <input
          type="email"
          placeholder="Email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          required
        />
        <input
          type="password"
          placeholder="Mot de passe"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          required
        />
        <input 
            type="text"
            placeholder="Prénom"
            value={prenom}
            onChange={(e) => setPrenom(e.target.value)}
            required
        />
        <input 
            type="text"
            placeholder="Nom"
            value={nom}
            onChange={(e) => setNom(e.target.value)}
            required
        />
        <input 
            type="date"
            placeholder="Début disponibilité"
            value={debutDispo}
            onChange={(e) => setDebutDispo(e.target.value)}
            required
        />
        <input 
            type="date"
            placeholder="Fin disponibilité"
            value={finDispo}
            onChange={(e) => setFinDispo(e.target.value)}
            required
        />
        <button type="submit">Créer</button>
      </form>

      {message && <p style={{ color: "green", marginTop: "10px" }}>{message}</p>}
      {error && <p style={{ color: "red", marginTop: "10px" }}>{error}</p>}



      <p>Vous êtes déjà inscrit ?</p>
      <button
        className="inscription-btn"
        onClick={() => navigate("/connexion")}
      >
        Connexion
      </button>

    </div>
  );
};

export default UserCreate;
