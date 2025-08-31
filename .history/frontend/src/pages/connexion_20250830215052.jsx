import { useState } from "react";

const Login = () => {
  const [courriel, setCourriel] = useState("");
  const [motDePasse, setmotDePasse] = useState("");
  const [error, setError] = useState("");

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(""); // reset erreur avant chaque soumission

    try {
      const response = await fetch("http://127.0.0.1:8000/api/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
            courriel: email,
            motDePasse: password,
            }),

      });

      const data = await response.json();

      if (!response.ok) {
        // Symfony renvoie souvent {error: "..."} ou une 401
        throw new Error(data.message || "Identifiants incorrects");
      }

      console.log("Réponse du backend :", data);

      // Si tu utilises JWT ou autre token
      if (data.token) {
        localStorage.setItem("token", data.token);
        alert("Connexion réussie ✅");
        window.location.href = "/profil"; // ou utilise useNavigate
      }

    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div>
      <h2>Connexion</h2>
      <form onSubmit={handleSubmit}>
        <input
          type="courriel"
          placeholder="Courriel"
          value={courriel}
          onChange={(e) => setCourriel(e.target.value)}
          required
        />
        <br />
        <input
          type="motDePasse"
          placeholder="Mot de passe"
          value={motDePasse}
          onChange={(e) => setmotDePasse(e.target.value)}
          required
        />
        <br />
        <button type="submit">Se connecter</button>
      </form>
      {error && <p style={{ color: "red" }}>{error}</p>}
    </div>
  );
};

export default Login;
