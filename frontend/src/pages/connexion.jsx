import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/connexion.module.css"; // ✅
import logo from '../images/logo.png';

const Connexion = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");

    try {
      const response = await fetch("http://127.0.0.1:8000/api/login_check", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "Identifiants incorrects");
      }

      if (data.token) {
        localStorage.setItem("token", data.token);
        alert("Connexion réussie ✅");
        navigate("/profil");
      }
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className={styles.connexionContainer}> 
      <img src={logo} alt="logo" className={styles.logo} />
      <h2>Connexion</h2>
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
        <button type="submit">Connexion</button>
      </form>

      <p>Vous n’êtes pas encore inscrit ?</p>
      <button
        className={styles.inscriptionBtn}
        onClick={() => navigate("/inscription")}
      >
        Inscription
      </button>

      {error && <p className={styles.error}>{error}</p>} {/* ✅ */}
    </div>
  );
};

export default Connexion;
