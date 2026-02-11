import { useReducer, useState } from "react";
import { useNavigate } from "react-router-dom";
import styles from "../style/register.module.css";
import logo from '../images/logo.png';

const API_URL = import.meta.env.VITE_API_URL;

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
  const [currentStep, setCurrentStep] = useState(1);
  const navigate = useNavigate();

  const handleChange = (field, value) => {
    dispatch({ field, value });
    if (errors[field]) {
      setErrors(prev => ({ ...prev, [field]: "" }));
    }
  };

  const nextStep = () => {
    if (validateStep(currentStep)) {
      setCurrentStep(prev => prev + 1);
    }
  };

  const prevStep = () => {
    setCurrentStep(prev => prev - 1);
  };

  const validateStep = (step) => {
    const newErrors = {};
    
    if (step === 1) {
      if (!formState.firstName.trim()) newErrors.firstName = "Prénom requis";
      if (!formState.lastName.trim()) newErrors.lastName = "Nom requis";
    }
    
    if (step === 2) {
      if (!formState.email.includes('@')) newErrors.email = "Email invalide";
      if (formState.password.length < 6) newErrors.password = "6 caractères minimum";
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateStep(3)) return;
    
    setIsLoading(true);

    try {
      const response = await fetch(`${API_URL}/api/userCreate`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(formState),
      });

      const data = await response.json();

      if (data.status) {
        setTimeout(() => {
          navigate("/login", { 
            state: { message: "Compte créé avec succès !" } 
          });
        }, 1500);
      } else {
        setErrors({ submit: data.message });
      }
    } catch (err) {
      setErrors({ submit: "Erreur réseau" });
    } finally {
      setIsLoading(false);
    }
  };

  const renderStep = () => {
    switch(currentStep) {
      case 1:
        return (
          <div className={styles.step}>
            <div className={styles.stepHeader}>
              <div className={styles.stepIndicator}>1</div>
              <h2>Qui êtes-vous ?</h2>
            </div>
            
            <div className={styles.inputGroup}>
              <label>Prénom</label>
              <input
                type="text"
                placeholder="Votre prénom"
                value={formState.firstName}
                onChange={(e) => handleChange('firstName', e.target.value)}
                className={errors.firstName ? styles.errorInput : ''}
              />
              {errors.firstName && <span className={styles.errorText}>{errors.firstName}</span>}
            </div>

            <div className={styles.inputGroup}>
              <label>Nom</label>
              <input
                type="text"
                placeholder="Votre nom"
                value={formState.lastName}
                onChange={(e) => handleChange('lastName', e.target.value)}
                className={errors.lastName ? styles.errorInput : ''}
              />
              {errors.lastName && <span className={styles.errorText}>{errors.lastName}</span>}
            </div>

            <button type="button" className={styles.primaryButton} onClick={nextStep}>
              Continuer
            </button>
          </div>
        );

      case 2:
        return (
          <div className={styles.step}>
            <div className={styles.stepHeader}>
              <div className={styles.stepIndicator}>2</div>
              <h2>Votre compte</h2>
            </div>

            <div className={styles.inputGroup}>
              <label>Email</label>
              <input
                type="email"
                placeholder="exemple@email.com"
                value={formState.email}
                onChange={(e) => handleChange('email', e.target.value)}
                className={errors.email ? styles.errorInput : ''}
              />
              {errors.email && <span className={styles.errorText}>{errors.email}</span>}
            </div>

            <div className={styles.inputGroup}>
              <label>Mot de passe</label>
              <input
                type="password"
                placeholder="6 caractères minimum"
                value={formState.password}
                onChange={(e) => handleChange('password', e.target.value)}
                className={errors.password ? styles.errorInput : ''}
              />
              {errors.password && <span className={styles.errorText}>{errors.password}</span>}
            </div>

            <div className={styles.buttonGroup}>
              <button type="button" className={styles.secondaryButton} onClick={prevStep}>
                Retour
              </button>
              <button type="button" className={styles.primaryButton} onClick={nextStep}>
                Continuer
              </button>
            </div>
          </div>
        );

      case 3:
        return (
          <div className={styles.step}>
            <div className={styles.stepHeader}>
              <div className={styles.stepIndicator}>3</div>
              <h2>Vos disponibilités</h2>
            </div>

            <div className={styles.inputGroup}>
              <label>Date de début</label>
              <input
                type="date"
                value={formState.availabilityStart}
                onChange={(e) => handleChange('availabilityStart', e.target.value)}
              />
            </div>

            <div className={styles.inputGroup}>
              <label>Date de fin</label>
              <input
                type="date"
                value={formState.availabilityEnd}
                onChange={(e) => handleChange('availabilityEnd', e.target.value)}
              />
            </div>

            <div className={styles.inputGroup}>
              <label>Compétences</label>
              <input
                type="text"
                placeholder="JavaScript, React, Node.js..."
                value={formState.skills}
                onChange={(e) => handleChange('skills', e.target.value)}
              />
              <small>Séparez par des virgules</small>
            </div>

            <div className={styles.buttonGroup}>
              <button type="button" className={styles.secondaryButton} onClick={prevStep}>
                Retour
              </button>
              <button type="submit" className={styles.primaryButton} disabled={isLoading}>
                {isLoading ? "Création..." : "Créer mon compte"}
              </button>
            </div>
          </div>
        );
    }
  };

  return (
    <div className={styles.container}>
      {/* Header simple et clean */}
      <div className={styles.header}>
        <img src={logo} alt="OpenHub" className={styles.logo} />
        <h1>Rejoindre OpenHub</h1>
        <p>Créez votre profil en 3 étapes</p>
      </div>

      {/* Progress bar */}
      <div className={styles.progressBar}>
        <div 
          className={styles.progress} 
          style={{ width: `${(currentStep / 3) * 100}%` }}
        ></div>
      </div>

      {/* Formulaire étape par étape */}
      <form onSubmit={handleSubmit} className={styles.form}>
        {renderStep()}
      </form>

      {/* Lien de connexion */}
      <div className={styles.loginLink}>
        <p>Déjà un compte ?</p>
        <button 
          type="button"
          className={styles.linkButton}
          onClick={() => navigate("/login")}
        >
          Se connecter
        </button>
      </div>

      {/* Messages d'erreur globaux */}
      {errors.submit && (
        <div className={styles.errorMessage}>
          {errors.submit}
        </div>
      )}
    </div>
  );
};

export default Register;