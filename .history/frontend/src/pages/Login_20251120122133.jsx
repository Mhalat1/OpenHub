// ...existing code...
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/login.module.css"; 
import logo from '../images/logo.png';

const API_URL = import.meta.env.VITE_API_URL;

const Login = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    console.log("Form submitted!"); // debug
    console.log("POST", `${API_URL}/api/login_check`, { email, password });

    setError("");

    try {
      const response = await fetch(`${API_URL}/api/login_check`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      // Essaie de parser la réponse en JSON (plus fiable que read text + parse)
      let data;
      try {
        data = await response.json();
      } catch {
        throw new Error("Server error: Invalid response format");
      }

      if (!response.ok) {
        // Lexik renvoie souvent 401 + { "code": 401, "message": "..." }
        throw new Error(data.message || "Invalid credentials");
      }

      if (data.token) {
        localStorage.setItem("token", data.token);
        alert("Login successful ✅");
        navigate("/profil");
        console.log("Login successful, token stored.", { token: data.token });
      } else {
        throw new Error("No token returned by server");
      }
    } catch (err) {
      setError(err.message);
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
          />
          <input
            type="password"
            placeholder="Password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
          />
          <button type="submit">Login</button>
        </form>

        <p className={styles.loginpageText}>Not registered yet?</p>
        <button
          className={styles.registerBtn}
          onClick={() => navigate("/register")}
        >
          Register
        </button>

        {error && <p className={styles.error}>{error}</p>}
      </div>
    </div>
  );
};

export default Login;