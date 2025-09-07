import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/inscription.module.css"; // ✅ CSS module
import logo from '../images/logo.png';

const UserCreate = () => {
  const [prenom, setPrenom] = useState("");
  const [nom, setNom] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [debutDispo, setDebutDispo] = useState("");
  const [finDispo, setFinDispo] = useState("");
  const [compétences, setCompetences] = useState("");

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
          email,
          password,
          prenom,
          nom,
          debutDispo,
          finDispo,
          compétences
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
        setCompetences("");
        navigate("/connexion");
      } else {
        setError(data.message);
      }
    } catch (err) {
      setError("Erreur lors de la création de l'utilisateur");
      console.error(err);
    }
  };

  return (
    <div className={styles.inscriptionContainer}>
      <h2>Créer un utilisateur</h2>
      <img src={logo} alt="logo" className={styles.logo} />
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
        < input
          type="text"
          placeholder="Compétences (séparées par des virgules)"
          value={compétences}
          onChange={(e) => setCompetences(e.target.value)}
        />
        <button type="submit">Créer</button>
      </form>

      {message && <p className={styles.success}>{message}</p>}
      {error && <p className={styles.error}>{error}</p>}

      <p>Vous êtes déjà inscrit ?</p>
      <button
        className={styles.connexionBtn}
        onClick={() => navigate("/connexion")}
      >
        Connexion
      </button>
    </div>
  );
};

export default UserCreate;
