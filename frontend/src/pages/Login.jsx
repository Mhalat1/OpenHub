import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/login.module.css";
import logo from "../images/logo.png";

const API_URL = import.meta.env.VITE_API_URL;
const Login = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setIsLoading(true);

    // Validation côté client
    if (!email || !password) {
      console.log("Empty fields");
      setError("Veuillez remplir tous les champs");
      setIsLoading(false);
      return;
    }

    if (!email.includes("@")) {
      setError("Invalid email format");
      setIsLoading(false);
      return;
    }
    try {
      const response = await fetch(`${API_URL}/api/login_check`, {
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
        localStorage.setItem("user_email", email); // Pour l'UX
        navigate("/home", {
          state: { message: "Connexion réussie !" },
        });
      } else {
        throw new Error("Token manquant dans la réponse");
      }
    } catch (err) {
      setError(err.message || "Erreur de connexion au serveur");
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className={styles.loginContainer}>
      {/* Section Branding */}
      <div className={styles.heroSection}>
        <div className={styles.logoContainer}>
          <img src={logo} alt="OpenHub Logo" className={styles.logo} />
        </div>
        <div className={styles.heroText}>
          <h1>Bienvenue sur OpenHub</h1>
          <p>Rejoignez la communauté des développeurs passionnés</p>
          <div className={styles.features}>
            <div className={styles.featureItem}>
              <span>Connectez avec des développeurs</span>
            </div>
            <div className={styles.featureItem}>
              <span>Partagez vos projets</span>
            </div>
            <div className={styles.featureItem}>
              <span>Collaborez en temps réel</span>
            </div>
          </div>
        </div>
      </div>

      {/* Section Formulaire */}
      <div className={styles.formSection}>
        <div className={styles.formContainer}>
          <div className={styles.formHeader}>
            <h2>Connexion</h2>
            <p>Accédez à votre espace personnel</p>
          </div>

          <form onSubmit={handleSubmit} className={styles.form} noValidate>
            <div className={styles.inputGroup}>
              <label htmlFor="email" className={styles.label}>
                Adresse email
              </label>
              <input
                id="email"
                type="email"
                placeholder="votre@email.com"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className={styles.input}
                disabled={isLoading}
              />
            </div>

            <div className={styles.inputGroup}>
              <label htmlFor="password" className={styles.label}>
                Mot de passe
              </label>
              <input
                id="password"
                type="password"
                placeholder="Votre mot de passe"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className={styles.input}
                disabled={isLoading}
              />
            </div>

            <button
              type="submit"
              className={`${styles.submitButton} ${isLoading ? styles.loading : ""}`}
              disabled={isLoading}
            >
              {isLoading ? (
                <>
                  <div className={styles.spinner}></div>
                  Connexion...
                </>
              ) : (
                "Se connecter"
              )}
            </button>
          </form>

          {/* Messages d'état */}
          {error && (
            <div className={styles.errorMessage}>
              <span className={styles.errorIcon}>⚠️</span>
              {error}
            </div>
          )}

          {/* Section d'inscription */}
          <div className={styles.registerSection}>
            <div className={styles.divider}>
              <span>Nouveau ici ?</span>
            </div>
            <button
              className={styles.registerButton}
              onClick={() => navigate("/register")}
              disabled={isLoading}
            >
              Créer un compte
            </button>
          </div>

          {/* Lien mot de passe oublié */}
          <div className={styles.forgotPassword}>
            <button
              className={styles.forgotLink}
              onClick={() => navigate("/reset-password")}
            >
              Mot de passe oublié ?
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Login;
