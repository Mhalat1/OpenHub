import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import utilisateurphoto from '../images/utilisateurs/user1.png';
import styles from '../style/Profil.module.css';

const Profil = () => {
  const [isEditing, setIsEditing] = useState(false);
  const [userData, setUserData] = useState({
    prenom: "Jean",
    nom: "Dupont",
    page: "Designer UX/UI",
    contributions: "24 projets",
    module: "Design Interactif",
    projets: "12 réalisés",
    competences: "Figma, React, CSS",
    disponibilites: "Temps plein",
    disponibiliteDebut: "",
    disponibiliteFin: ""
  });
  const navigate = useNavigate();

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setUserData(prevState => ({
      ...prevState,
      [name]: value
    }));
  };

  const handleSave = () => {
    setIsEditing(false);
    // Ajouter la logique pour sauvegarder les données si nécessaire
  };

  return (
    <div className={styles.profilContainer}>
      <div className={styles.profilHeader}>
        <img src={utilisateurphoto} alt="Photo de profil" className={styles.profilPhoto} />
        <h1>Niveau 7</h1>
      </div>

      <div className={styles.profilContent}>
        <div className={styles.profilInfo}>
          <div className={styles.infoSection}>

              <h2>{userData.prenom}</h2>
            
              <h2>{userData.nom}</h2>
          
          </div>
        </div>

        <div className={styles.disponibilitesSection}>
          <h3>Disponibilités</h3>
                  <button 
          className={styles.editButton}
          onClick={() => setIsEditing(!isEditing)}
        >
          {isEditing ? 'Annuler' : 'Modifier'}
        </button>
          <div className={styles.dateRange}>
            <div className={styles.dateInput}>
              <span>Du:</span>
              {isEditing ? (
                <input
                  type="date"
                  name="disponibiliteDebut"
                  value={userData.disponibiliteDebut}
                  onChange={handleInputChange}
                  className={styles.editInput}
                />
              ) : (
                <span>{userData.disponibiliteDebut || "01/01/2023"}</span>
              )}
            </div>
            <div className={styles.dateInput}>
              <span>Au:</span>
              {isEditing ? (
                <input
                  type="date"
                  name="disponibiliteFin"
                  value={userData.disponibiliteFin}
                  onChange={handleInputChange}
                  className={styles.editInput}
                />
              ) : (
                <span>{userData.disponibiliteFin || "31/12/2023"}</span>
              )}
            </div>
          </div>
        </div>

        <div className={styles.actionsSection}>
          <button 
            className={styles.actionButton} 
            onClick={() => navigate("/projets")} 
          >
            Page Contributions
          </button>
          <button 
            className={styles.actionButton} 
            onClick={() => navigate("/projets")} 
          >
            Page Contributions
          </button>
          <button 
            className={styles.actionButton} 
            onClick={() => navigate("/projets")} 
          >
            Modale Projets
          </button>
          <button 
            className={styles.actionButton} 
            onClick={() => navigate("/projets")} 
          >
            Modale Compétences
          </button>
        </div>
      </div>

      {isEditing && (
        <div className={styles.saveSection}>
          <button className={styles.saveButton} onClick={handleSave}>
            Enregistrer les modifications
          </button>
        </div>
      )}
    </div>
  );
};

export default Profil;
