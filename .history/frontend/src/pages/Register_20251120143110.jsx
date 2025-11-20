import { useReducer, useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/register.module.css";
import logo from '../images/logo.png';

const API_URL = import.meta.env.VITE_API_URL;

// State management professionnel
const initialState = {
  firstName: "", lastName: "", email: "", password: "",
  availabilityStart: "", availabilityEnd: "", skills: ""
};

function formReducer(state, action) {
  return { ...state, [action.field]: action.value };
}

const Register = () => {
  const [formState, dispatch] = useReducer(formReducer, initialState);
  const [errors, setErrors] = useState({});
  const [isLoading, setIsLoading] = useState(false);
  const navigate = useNavigate();

  const handleChange = (field, value) => {
    dispatch({ field, value });
    // Validation temps réel
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: "" }));
    }
  };

  const validateForm = () => {
    const newErrors = {};
    
    if (!formState.email.includes('@')) {
      newErrors.email = "Format d'email invalide";
    }
    if (formState.password.length < 6) {
      newErrors.password = "6 caractères minimum";
    }
    if (!formState.firstName.trim()) {
      newErrors.firstName = "Prénom requis";
    }
    if (formState.availabilityStart && formState.availabilityEnd) {
      if (new Date(formState.availabilityStart) > new Date(formState.availabilityEnd)) {
        newErrors.availability = "La date de fin doit être après la date de début";
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) return;
    
    setIsLoading(true);

    try {
      const response = await fetch(`${API_URL}/api/userCreate`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(formState),
      });

      const data = await response.json();

      if (data.status) {
        // Succès avec feedback enrichi
        setTimeout(() => {
          navigate("/login", { 
            state: { message: "Compte créé avec succès ! Vous pouvez maintenant vous connecter." } 
          });
        }, 1500);
      } else {
        setErrors({ submit: data.message });
      }
    } catch (err) {
      setErrors({ submit: "Erreur réseau - réessayez" });
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className={styles.registerContainer}>
      {/* Header cohérent avec Login */}
      <div className={styles.heroSection}>
        <img src={logo} alt="OpenHub" className={styles.logo} />
        <div className={styles.heroText}>
          <h1>Rejoindre OpenHub</h1>
          <p>Créez votre profil développeur</p>
        </div>
      </div>

      <form onSubmit={handleSubmit} className={styles.form}>
        {/* Section Informations Personnelles */}
        <div className={styles.formSection}>
          <h3>Informations personnelles</h3>
          <div className={styles.row}>
            <div className={styles.inputGroup}>
              <label htmlFor="firstName">Prénom</label>
              <input
                id="firstName"
                type="text"
                value={formState.firstName}
                onChange={(e) => handleChange('firstName', e.target.value)}
                disabled={isLoading}
                className={errors.firstName ? styles.errorInput : ''}
              />
              {errors.firstName && <span className={styles.errorText}>{errors.firstName}</span>}
            </div>
            
            <div className={styles.inputGroup}>
              <label htmlFor="lastName">Nom</label>
              <input
                id="lastName"
                type="text"
                value={formState.lastName}
                onChange={(e) => handleChange('lastName', e.target.value)}
                disabled={isLoading}
              />
            </div>
          </div>
        </div>

        {/* Section Compte */}
        <div className={styles.formSection}>
          <h3>Compte</h3>
          <div className={styles.inputGroup}>
            <label htmlFor="email">Email</label>
            <input
              id="email"
              type="email"
              placeholder="exemple@email.com"
              value={formState.email}
              onChange={(e) => handleChange('email', e.target.value)}
              disabled={isLoading}
              className={errors.email ? styles.errorInput : ''}
            />
            {errors.email && <span className={styles.errorText}>{errors.email}</span>}
          </div>

          <div className={styles.inputGroup}>
            <label htmlFor="password">Mot de passe</label>
            <input
              id="password"
              type="password"
              placeholder="6 caractères minimum"
              value={formState.password}
              onChange={(e) => handleChange('password', e.target.value)}
              disabled={isLoading}
              className={errors.password ? styles.errorInput : ''}
            />
            {errors.password && <span className={styles.errorText}>{errors.password}</span>}
          </div>
        </div>

        {/* Section Disponibilité */}
        <div className={styles.formSection}>
          <h3>Disponibilité</h3>
          <div className={styles.row}>
            <div className={styles.inputGroup}>
              <label htmlFor="availabilityStart">Date de début</label>
              <input
                id="availabilityStart"
                type="date"
                value={formState.availabilityStart}
                onChange={(e) => handleChange('availabilityStart', e.target.value)}
                disabled={isLoading}
              />
            </div>
            
            <div className={styles.inputGroup}>
              <label htmlFor="availabilityEnd">Date de fin</label>
              <input
                id="availabilityEnd"
                type="date"
                value={formState.availabilityEnd}
                onChange={(e) => handleChange('availabilityEnd', e.target.value)}
                disabled={isLoading}
              />
            </div>
          </div>
          {errors.availability && <span className={styles.errorText}>{errors.availability}</span>}
        </div>

        {/* Section Compétences */}
        <div className={styles.formSection}>
          <h3>Compétences</h3>
          <div className={styles.inputGroup}>
            <label htmlFor="skills">Technologies maîtrisées</label>
            <input
              id="skills"
              type="text"
              placeholder="JavaScript, React, Node.js, Python..."
              value={formState.skills}
              onChange={(e) => handleChange('skills', e.target.value)}
              disabled={isLoading}
            />
            <small>Séparez par des virgules</small>
          </div>
        </div>

        {/* Actions */}
        <button 
          type="submit" 
          className={styles.submitButton}
          disabled={isLoading}
        >
          {isLoading ? "Création du compte..." : "Créer mon compte"}
        </button>

        {errors.submit && (
          <div className={styles.submitError}>{errors.submit}</div>
        )}

        <div className={styles.loginRedirect}>
          <p>Déjà un compte ?</p>
          <button 
            type="button"
            className={styles.loginButton}
            onClick={() => navigate("/login")}
            disabled={isLoading}
          >
            Se connecter
          </button>
        </div>
      </form>
    </div>
  );
};

export default Register;