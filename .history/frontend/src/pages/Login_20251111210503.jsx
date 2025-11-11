import { useState } from "react";
import { useNavigate } from "react-router-dom";
import logo from '../images/logo.png';
import styles from "../style/login.module.css";

const API_URL = import.meta.env.VITE_API_URL;

const Login = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    console.log("Form submitted!");
    console.log("POST", `${API_URL}/api/login_check`, { email, password });

    setError("");
    setLoading(true);

    try {
      const response = await fetch(`${API_URL}/api/login_check`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      console.log("Response status:", response.status);
      
      const text = await response.text();
      console.log("Raw response:", text);

      let data;
      try {
        data = text ? JSON.parse(text) : {};
      } catch (parseError) {
        console.error("JSON parse error:", parseError);
        throw new Error("Server error: Invalid response format");
      }

      console.log("Parsed data:", data);

      if (!response.ok) {
        throw new Error(data.message || `HTTP error! status: ${response.status}`);
      }

      // Vérification plus robuste du token
      if (!data.token) {
        console.warn("No token in response:", data);
        throw new Error("Authentication failed: No token received");
      }

      // Stockage du token
      localStorage.setItem("token", data.token);
      console.log("Token stored successfully");
      
      alert("Login successful ✅");
      
      // Redirection avec timeout pour s'assurer que tout est prêt
      setTimeout(() => {
        navigate("/profil");
      }, 100);

    } catch (err) {
      console.error("Login error:", err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className={styles.loginContainer}> 
      <div className={styles.logo}>
        <img src={logo} alt="logo" className={styles.logo} />
      </div>

      <div className={styles.divider}></div> 

      <div className={styles.logincontent}>
        <h2>Login</h2>
        <form onSubmit={handleSubmit}>
          <input
            type="email"
            placeholder="Email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            disabled={loading}
          />
          <input
            type="password"
            placeholder="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            disabled={loading}
          />
          <button type="submit" disabled={loading}>
            {loading ? "Logging in..." : "Login"}
          </button>
        </form>

        <p className={styles.loginpageText}>Not registered yet?</p>
        <button
          className={styles.registerBtn}
          onClick={() => navigate("/register")}
          disabled={loading}
        >
          Register
        </button>

        {error && <p className={styles.error}>{error}</p>}
      </div>
    </div>
  );
};

export default Login;