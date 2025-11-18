// ...existing code...
import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/login.module.css"; 
import logo from '../images/logo.png';
import { useEffect } from 'react';

const API_URL = import.meta.env.VITE_API_URL;

const Login = () => {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [debugInfo, setDebugInfo] = useState("");
  const navigate = useNavigate();

  useEffect(() => {
    localStorage.removeItem("token");
  }, []);

  // const handleSubmit = async (e) => {
  //   e.preventDefault();
  //   localStorage.removeItem("token");
    
  //   console.log("🔍 DEBUG: Form submitted");
  //   console.log("📧 Email:", email);
  //   console.log("🔐 Password length:", password.length);
  //   console.log("🌐 API_URL:", API_URL);
    
  //   setError("");
  //   setDebugInfo("");

  //   try {
  //     const requestBody = JSON.stringify({ email, password });
  //     console.log("📤 Request body:", requestBody);

  //     const response = await fetch(`${API_URL}/api/login_check`, {
  //       method: "POST",
  //       headers: {
  //         "Content-Type": "application/json",
  //       },
  //       body: requestBody,
  //     });

  //     console.log("📥 Response status:", response.status);
  //     console.log("📥 Response headers:", Object.fromEntries(response.headers.entries()));

  //     // Récupérer le texte brut de la réponse
  //     const responseText = await response.text();
  //     console.log("📥 Raw response:", responseText);

  //     let data;
  //     try {
  //       data = responseText ? JSON.parse(responseText) : {};
  //       console.log("📥 Parsed data:", data);
  //     } catch (parseError) {
  //       console.error("❌ JSON parse error:", parseError);
  //       throw new Error(`Server returned invalid JSON: ${responseText.substring(0, 100)}`);
  //     }

  //     // Debug info détaillée
  //     const debugData = {
  //       status: response.status,
  //       statusText: response.statusText,
  //       url: `${API_URL}/api/login_check`,
  //       requestBody: { email, password: '***' }, // Masquer le mot de passe
  //       responseData: data,
  //       responseHeaders: Object.fromEntries(response.headers.entries()),
  //       timestamp: new Date().toISOString()
  //     };

  //     setDebugInfo(JSON.stringify(debugData, null, 2));
  //     console.log("🐛 DEBUG COMPLET:", debugData);

  //     if (!response.ok) {
  //       // Analyser l'erreur en détail
  //       const errorDetails = {
  //         status: response.status,
  //         message: data.message || data.error || "Unknown error",
  //         code: data.code || "no_code",
  //         details: data
  //       };
        
  //       console.error("❌ Authentication failed:", errorDetails);
        
  //       if (response.status === 401) {
  //         throw new Error(`Accès refusé (401): ${errorDetails.message}`);
  //       } else if (response.status === 500) {
  //         throw new Error(`Erreur serveur (500): ${errorDetails.message}`);
  //       } else {
  //         throw new Error(`Erreur ${response.status}: ${errorDetails.message}`);
  //       }
  //     }

  //     // Vérifier la présence du token
  //     if (data.token) {
  //       console.log("✅ Token received:", data.token.substring(0, 50) + "...");
  //       localStorage.setItem("token", data.token);
        
  //       // Stocker les infos user si disponibles
  //       if (data.user) {
  //         localStorage.setItem("user", JSON.stringify(data.user));
  //       }
        
  //       alert("✅ Connexion réussie !");
  //       console.log("🚀 Navigation vers /profil");
  //       navigate("/profil");
        
  //     } else {
  //       console.error("❌ NO TOKEN in response:", data);
        
  //       // Analyser pourquoi pas de token
  //       const tokenAnalysis = {
  //         hasToken: !!data.token,
  //         responseKeys: Object.keys(data),
  //         responseSize: JSON.stringify(data).length,
  //         possibleIssues: [
  //           "Backend JWT mal configuré",
  //           "Success handler ne génère pas de token", 
  //           "Route configurée incorrectement",
  //           "Problème de sécurité CORS"
  //         ]
  //       };
        
  //       console.error("🔍 Token analysis:", tokenAnalysis);
  //       throw new Error(`Aucun token reçu du serveur. Réponse: ${JSON.stringify(data)}`);
  //     }
      
  //   } catch (err) {
  //     console.error("💥 Login error:", err);
  //     setError(err.message);
      
  //     // Debug supplémentaire pour les erreurs réseau
  //     if (err.name === 'TypeError' && err.message.includes('fetch')) {
  //       setError("Erreur réseau - Vérifiez la connexion et l'URL de l'API");
  //     }
  //   }
  // };

  return (
    <div className={styles.loginContainer}> 
      <div className={styles.logo}>
        <img src={logo} alt="logo" className={styles.logo} />
      </div>

      <div className={styles.divider}></div> 

      <div className={styles.logincontent}>
        <h2>Login</h2>
        
        {/* Affichage des infos de debug */}
        {debugInfo && (
          <div style={{ 
            background: '#f5f5f5', 
            padding: '10px', 
            margin: '10px 0', 
            borderRadius: '5px',
            fontSize: '12px',
            maxHeight: '200px',
            overflow: 'auto'
          }}>
            <strong>🐛 Debug Info:</strong>
            <pre>{debugInfo}</pre>
          </div>
        )}

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

        {error && (
          <div className={styles.error}>
            <strong>❌ Erreur:</strong> {error}
            <br />
            <small>Ouvrez la console (F12) pour plus de détails</small>
          </div>
        )}
      </div>
    </div>
  );
};

export default Login;