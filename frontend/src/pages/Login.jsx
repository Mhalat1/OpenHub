import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/login.module.css"; // ✅ updated to English
import logo from '../images/logo.png';

const Login = () => {
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
        throw new Error(data.message || "Invalid credentials");
      }

      if (data.token) {
        localStorage.setItem("token", data.token);
        alert("Login successful ✅");
        navigate("/profil");
      }
    } catch (err) {
      setError(err.message);
    }
  };

  return (
    <div className={styles.loginContainer}> 
      <img src={logo} alt="logo" className={styles.logo} />
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

      <p>Not registered yet?</p>
      <button
        className={styles.registerBtn}
        onClick={() => navigate("/register")}
      >
        Register
      </button>

      {error && <p className={styles.error}>{error}</p>} {/* ✅ */}
    </div>
  );
};

export default Login;
