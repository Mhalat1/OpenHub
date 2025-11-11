import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import logo from '../images/logo.png';
import styles from "../style/login.module.css";

const API_URL = import.meta.env.VITE_API_URL;

const Login = () => {
  const [email, setEmail] = useState("test@example.com");
  const [password, setPassword] = useState("password123");
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  // Fonction pour créer un utilisateur de test
  const createTestUser = async () => {
    try {
      console.log("Creating test user...");
      const response = await fetch(`${API_URL}/api/userCreate`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          email: "test@example.com",
          password: "password123",
          firstname: "Test",
          lastname: "User"
        }),
      });
      
      const result = await response.json();
      console.log("User creation result:", result);
      
      if (response.ok) {
        console.log("✅ Test user created successfully");
      } else {
        console.warn("⚠️ User may already exist:", result.message);
      }
    } catch (err) {
      console.error("User creation error:", err);
    }
  };

  // Créer un utilisateur de test au chargement
  useEffect(() => {
    createTestUser();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    console.log("Trying login with:", { email, password });

    setError("");
    setLoading(true);

    try {
      // Test 1: Vérifiez d'abord que l'utilisateur existe
      console.log("Step 1: Checking if user exists...");
      
      // Test 2: Essayez le login
      const response = await fetch(`${API_URL}/api/login_check`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ 
          email: email,
          password: password 
        }),
      });

      console.log("Step 2: Login response status:", response.status);
      
      const text = await response.text();
      console.log("Step 3: Raw response:", text);

      let data;
      try {
        data = text ? JSON.parse(text) : {};
        console.log("Step 4: Parsed data:", data);
      } catch (parseError) {
        console.error("JSON parse error:", parseError);
        throw new Error("Server returned invalid JSON");
      }

      // Analyse détaillée de la réponse
      if (response.status === 200) {
        if (data.token && data.token !== "") {
          console.log("Step 5: Valid token received");
          localStorage.setItem("token", data.token);
          alert("Login successful ✅");
          navigate("/profil");
        } else {
          console.warn("Step 5: Empty token with status 200");
          throw new Error("Server authentication failed. User may not exist or password is wrong.");
        }
      } else {
        console.warn("Step 5: Non-200 status:", response.status);
        throw new Error(data.message || `Login failed with status ${response.status}`);
      }

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
        
        <div style={{ 
          background: '#f0f8ff', 
          padding: '10px', 
          borderRadius: '5px', 
          marginBottom: '15px',
          fontSize: '12px'
        }}>
          <strong>Test Credentials (auto-filled):</strong><br/>
          Email: test@example.com<br/>
          Password: password123
        </div>

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

        {error && (
          <div className={styles.error}>
            <strong>Authentication Failed</strong><br/>
            {error}
            <div style={{ marginTop: '10px', fontSize: '14px' }}>
              Possible causes:
              <ul>
                <li>User doesn't exist in database</li>
                <li>Password is incorrect</li>
                <li>JWT bundle configuration issue</li>
              </ul>
            </div>
          </div>
        )}

        <p className={styles.loginpageText}>Not registered yet?</p>
        <button
          className={styles.registerBtn}
          onClick={() => navigate("/register")}
          disabled={loading}
        >
          Register
        </button>
      </div>
    </div>
  );
};

// ⚠️ IMPORTANT : CETTE LIGNE DOIT ÊTRE PRÉSENTE ⚠️
export default Login;