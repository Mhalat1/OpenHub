import { useReducer, useState } from "react";
import { useNavigate } from "react-router-dom";
import logo from "../images/logo.png";
import styles from "../style/register.module.css";

const API_URL = import.meta.env.VITE_API_URL;

const initialState = {
  firstName: "",
  lastName: "",
  email: "",
  password: "",
  availabilityStart: "",
  availabilityEnd: "",
  skills: "",
};

function formReducer(state, action) {
  return { ...state, [action.field]: action.value };
}

// ── Validation identique au backend validateName ──────────────────────────────
const validateName = (name) => {
  const trimmed = name.trim();

  if (!trimmed)                          return "Champ requis";
  if (trimmed.length < 2)               return "Minimum 2 caractères";
  if (trimmed.length > 20)              return "Maximum 20 caractères";
  if (!/^[\p{L}\s'\-]+$/u.test(trimmed))
    return "Lettres, espaces, tirets et apostrophes uniquement";
  if (/^[\s\-']|[\s\-']$/.test(trimmed))
    return "Ne peut pas commencer ou finir par un espace, tiret ou apostrophe";
  if (/\d/.test(trimmed))               return "Les chiffres ne sont pas autorisés";
  if (/[\s'\-]{3,}/.test(trimmed))
    return "Pas plus de 2 espaces, tirets ou apostrophes consécutifs";

  return null; // valide
};

const validateEmail = (email) => {
    console.log('Validating email:', email);
  const trimmed = email.trim();
  
  if (!trimmed) return "Email requis";
  
  // Vérification de base
  if (!trimmed.includes('@')) {
    return "L'email doit contenir un @";
  }
  
  // Séparer en partie locale et domaine
  const parts = trimmed.split('@');
  if (parts.length !== 2) {
    return "Format d'email invalide";
  }
  
  const [local, domain] = parts;
  
  // Vérifier que les parties ne sont pas vides
  if (!local) {
    return "La partie locale de l'email ne peut pas être vide";
  }
  
  if (!domain) {
    return "Le domaine de l'email ne peut pas être vide";
  }
  
  // Vérifier que le domaine contient un point (TLD)
  if (!domain.includes('.')) {
    return "Le domaine doit contenir un point (ex: gmail.com)";
  }
  
  // Vérifier que le point n'est pas au début ou à la fin du domaine
  if (domain.startsWith('.') || domain.endsWith('.')) {
    return "Format de domaine invalide";
  }
  
  // Vérifier qu'il n'y a pas d'espaces
  if (trimmed.includes(' ')) {
    return "L'email ne peut pas contenir d'espaces";
  }
  
  // Validation plus stricte avec regex (même que PHP FILTER_VALIDATE_EMAIL)
  const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

    if (!emailRegex.test(trimmed)) {
    return "Format d'email invalide. Utilisez le format: nom@domaine.xxx (ex: jean@example.com)";
  }
  

  
  return null; // Email valide
};


const Register = () => {
  const [formState, dispatch] = useReducer(formReducer, initialState);
  const [errors, setErrors]   = useState({});
  const [isLoading, setIsLoading] = useState(false);
  const [currentStep, setCurrentStep] = useState(1);
  const navigate = useNavigate();

  const handleChange = (field, value) => {
    dispatch({ field, value });
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: "" }));
    }
  };

  const nextStep = () => {
    if (validateStep(currentStep)) {
      setCurrentStep((prev) => prev + 1);
    }
  };

  const prevStep = () => {
    setCurrentStep((prev) => prev - 1);
  };
const validateStep = (step) => {
  const newErrors = {};

  if (step === 1) {
    const firstNameError = validateName(formState.firstName);
    const lastNameError  = validateName(formState.lastName);
    if (firstNameError) newErrors.firstName = firstNameError;
    if (lastNameError)  newErrors.lastName  = lastNameError;
  }


      if (step === 2) {
      // ✅ Utilisation de la nouvelle validation d'email
      const emailError = validateEmail(formState.email);
      if (emailError) newErrors.email = emailError;
    }



  if (step === 3) {
    if (formState.availabilityStart && formState.availabilityEnd &&
        formState.availabilityStart >= formState.availabilityEnd)
      newErrors.availabilityEnd = "La date de fin doit être après la date de début";

    if (formState.availabilityStart) {
      const today = new Date().toISOString().split("T")[0];
      if (formState.availabilityStart < today)
        newErrors.availabilityStart = "La date de début doit être dans le futur";
    }
  }

  setErrors(newErrors);
  return Object.keys(newErrors).length === 0;
};

  const handleSubmit = async (e) => {

  e.preventDefault();
  
  // Valider l'étape 3 d'abord (pour les dates)
  if (!validateStep(3)) return;
  
  // Valider l'email et mot de passe à nouveau
  const emailError = validateEmail(formState.email);
  if (emailError) {
    setErrors(prev => ({ ...prev, email: emailError }));
    setCurrentStep(2); // Retourner à l'étape 2
    return;
  }
  
  // Valider le mot de passe
  if (!formState.password || formState.password.length < 6) {
    setErrors(prev => ({ ...prev, password: "Mot de passe invalide" }));
    setCurrentStep(2);
    return;
  }
  
  // Valider les noms
  const firstNameError = validateName(formState.firstName);
  const lastNameError = validateName(formState.lastName);
  if (firstNameError || lastNameError) {
    setErrors(prev => ({ 
      ...prev, 
      firstName: firstNameError || '',
      lastName: lastNameError || '' 
    }));
    setCurrentStep(1);
    return;
  }

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
            state: { message: "Compte créé avec succès !" },
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
    switch (currentStep) {
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
                onChange={(e) => handleChange("firstName", e.target.value)}
                className={errors.firstName ? styles.errorInput : ""}
                maxLength={20}
              />
              {errors.firstName && (
                <span className={styles.errorText}>{errors.firstName}</span>
              )}
            </div>

            <div className={styles.inputGroup}>
              <label>Nom</label>
              <input
                type="text"
                placeholder="Votre nom"
                value={formState.lastName}
                onChange={(e) => handleChange("lastName", e.target.value)}
                className={errors.lastName ? styles.errorInput : ""}
                maxLength={20}
              />
              {errors.lastName && (
                <span className={styles.errorText}>{errors.lastName}</span>
              )}
            </div>

            <button
              type="button"
              className={styles.primaryButton}
              onClick={nextStep}
            >
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
                onChange={(e) => handleChange("email", e.target.value)}
                className={errors.email ? styles.errorInput : ""}
              />
              {errors.email && (
                <span className={styles.errorText}>{errors.email}</span>
              )}
              <small className={styles.hint}>
                Format: nom@domaine.com (ex: jean.dupont@gmail.com)
              </small>
            </div>

            <div className={styles.inputGroup}>
              <label>Mot de passe</label>
              <input
                type="password"
                placeholder="6 caractères minimum"
                value={formState.password}
                onChange={(e) => handleChange("password", e.target.value)}
                className={errors.password ? styles.errorInput : ""}
              />
              {errors.password && (
                <span className={styles.errorText}>{errors.password}</span>
              )}
            </div>

            <div className={styles.buttonGroup}>
              <button
                type="button"
                className={styles.secondaryButton}
                onClick={prevStep}
              >
                Retour
              </button>
              <button
                type="button"
                className={styles.primaryButton}
                onClick={nextStep}
              >
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
                onChange={(e) =>
                  handleChange("availabilityStart", e.target.value)
                }
                className={errors.availabilityStart ? styles.errorInput : ""}
              />
              {errors.availabilityStart && (
                <span className={styles.errorText}>
                  {errors.availabilityStart}
                </span>
              )}
            </div>

            <div className={styles.inputGroup}>
              <label>Date de fin</label>
              <input
                type="date"
                value={formState.availabilityEnd}
                onChange={(e) =>
                  handleChange("availabilityEnd", e.target.value)
                }
                className={errors.availabilityEnd ? styles.errorInput : ""}
              />
              {errors.availabilityEnd && (
                <span className={styles.errorText}>
                  {errors.availabilityEnd}
                </span>
              )}
            </div>

            <div className={styles.inputGroup}>
              <label>Compétences</label>
              <input
                type="text"
                placeholder="JavaScript, React, Node.js..."
                value={formState.skills}
                onChange={(e) => handleChange("skills", e.target.value)}
              />
              <small>Séparez par des virgules</small>
            </div>

            <div className={styles.buttonGroup}>
              <button
                type="button"
                className={styles.secondaryButton}
                onClick={prevStep}
              >
                Retour
              </button>
              <button
                type="submit"
                className={styles.primaryButton}
                disabled={isLoading}
              >
                {isLoading ? "Création..." : "Créer mon compte"}
              </button>
            </div>
          </div>
        );
    }
  };

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <img src={logo} alt="OpenHub" className={styles.logo} />
        <h1>Rejoindre OpenHub</h1>
        <p>Créez votre profil en 3 étapes</p>
      </div>

      <div className={styles.progressBar}>
        <div
          className={styles.progress}
          style={{ width: `${(currentStep / 3) * 100}%` }}
        ></div>
      </div>

      <form onSubmit={handleSubmit} className={styles.form}>
        {renderStep()}
      </form>

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

      {errors.submit && (
        <div className={styles.errorMessage}>{errors.submit}</div>
      )}
    </div>
  );
};

export default Register;