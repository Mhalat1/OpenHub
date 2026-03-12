import React, { useState } from "react";

const DonatePage = () => {
  const [amount, setAmount] = useState(5);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  const handleDonate = async () => {
    setLoading(true);
    setError("");

    try {
      const response = await fetch(
        `${import.meta.env.VITE_API_URL}/api/donate`,
        {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ amount }),
        },
      );

      if (!response.ok) {
        throw new Error(
          "Erreur lors de la création de la session de paiement.",
        );
      }

      const data = await response.json();
      window.location.href = data.url; // Redirection vers Stripe
    } catch (err) {
      setError(err.message);
      setLoading(false);
    }
  };

  return (
    <div style={styles.container}>
      <h1 style={styles.title}>💖 Soutenir le projet OpenHub</h1>
      <p style={styles.text}>
        OpenHub est un projet open source maintenu avec passion. Vos dons
        permettent de couvrir les coûts d’hébergement et de développement.
      </p>

      <div style={styles.donationBox}>
        <label style={styles.label}>Montant du don (€) :</label>
        <input
          type="number"
          value={amount}
          onChange={(e) => setAmount(e.target.value)}
          min="1"
          step="1"
          style={styles.input}
        />

        <button onClick={handleDonate} style={styles.button} disabled={loading}>
          {loading ? "Redirection..." : "Faire un don 💸"}
        </button>

        {error && <p style={styles.error}>{error}</p>}
      </div>
    </div>
  );
};

const styles = {
  container: {
    maxWidth: "600px",
    margin: "80px auto",
    textAlign: "center",
    background: "#f9fafb",
    borderRadius: "16px",
    padding: "40px 20px",
    boxShadow: "0 4px 20px rgba(0,0,0,0.1)",
  },
  title: { fontSize: "1.8rem", color: "#222", marginBottom: "16px" },
  text: { color: "#555", marginBottom: "30px" },
  donationBox: { display: "flex", flexDirection: "column", gap: "12px" },
  label: { fontWeight: "bold", color: "#333" },
  input: {
    padding: "10px",
    border: "1px solid #ccc",
    borderRadius: "8px",
    fontSize: "1rem",
  },
  button: {
    backgroundColor: "#635bff",
    color: "white",
    border: "none",
    borderRadius: "8px",
    padding: "12px 20px",
    cursor: "pointer",
    fontSize: "1rem",
    transition: "background-color 0.3s ease",
  },
  error: { color: "red", marginTop: "10px" },
};

export default DonatePage;
