import { useState } from "react";
import { useNavigate } from "react-router-dom";
import "../style/connexion.css";
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
        body: JSON.stringify({
          email,
          password,
        }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || "Identifiants incorrects");
      }

      if (data.token) {
        localStorage.setItem("token", data.token); // Stockage du JWT
        alert("Connexion réussie ✅");
        navigate("/profil"); // Redirection vers profil
      }

    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className="connexion-container">
      <img src={logo} alt="logo" />
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
        className="inscription-btn"
        onClick={() => navigate("/inscription")}
      >
        Inscription
      </button>

      {error && <p className="error">{error}</p>}
    </div>
  );
};

export default Connexion;
