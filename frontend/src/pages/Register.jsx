import { useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/register.module.css"; // updated CSS module
import logo from '../images/logo.png';

const Register = () => {
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [availabilityStart, setAvailabilityStart] = useState("");
  const [availabilityEnd, setAvailabilityEnd] = useState("");
  const [skills, setSkills] = useState("");

  const [successMessage, setSuccessMessage] = useState("");
  const [errorMessage, setErrorMessage] = useState("");

  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setSuccessMessage("");
    setErrorMessage("");

    try {
      const response = await fetch("http://127.0.0.1:8000/api/userCreate", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          email,
          password,
          firstName,
          lastName,
          availabilityStart,
          availabilityEnd,
          skills,
        }),
      });

      const data = await response.json();

      if (data.status) {
        setSuccessMessage(data.message);
        // Reset form fields
        setEmail("");
        setPassword("");
        setFirstName("");
        setLastName("");
        setAvailabilityStart("");
        setAvailabilityEnd("");
        setSkills("");
        // Navigate to login page
        navigate("/login");
      } else {
        setErrorMessage(data.message);
      }
    } catch (err) {
      setErrorMessage("Error while creating the account");
      console.error(err);
    }
  };

  return (
    <div className={styles.registerContainer}>
      <img src={logo} alt="Logo" className={styles.logo} />

      <form onSubmit={handleSubmit}>
        <input
          type="email"
          placeholder="Email address"
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
        <input
          type="text"
          placeholder="First name"
          value={firstName}
          onChange={(e) => setFirstName(e.target.value)}
          required
        />
        <input
          type="text"
          placeholder="Last name"
          value={lastName}
          onChange={(e) => setLastName(e.target.value)}
          required
        />
        <input
          type="date"
          placeholder="Availability start"
          value={availabilityStart}
          onChange={(e) => setAvailabilityStart(e.target.value)}
          required
        />
        <input
          type="date"
          placeholder="Availability end"
          value={availabilityEnd}
          onChange={(e) => setAvailabilityEnd(e.target.value)}
          required
        />
        <input
          type="text"
          placeholder="Skills (comma separated)"
          value={skills}
          onChange={(e) => setSkills(e.target.value)}
        />
        <button type="submit">Create account</button>
      </form>

      {successMessage && <p className={styles.success}>{successMessage}</p>}
      {errorMessage && <p className={styles.error}>{errorMessage}</p>}

      <p>Already have an account?</p>
      <button
        className={styles.loginBtn}
        onClick={() => navigate("/login")}
      >
        Log in
      </button>
    </div>
  );
};

export default Register;
