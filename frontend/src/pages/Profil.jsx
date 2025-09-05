import React, { useState } from 'react';
import '../style/Profil.css';

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

  const handleInputChange = (e) => {
    const { name, value } = e.target;
    setUserData(prevState => ({
      ...prevState,
      [name]: value
    }));
  };

  const handleSave = () => {
    setIsEditing(false);
    // Ici, on pourrait ajouter une logique pour sauvegarder les données
  };

  return (
    <div className="profil-container">
      <div className="profil-header">
        <h1>Niveau 7</h1>
        <button 
          className="edit-button"
          onClick={() => setIsEditing(!isEditing)}
        >
          {isEditing ? 'Annuler' : 'Modifier'}
        </button>
      </div>

      <div className="profil-content">
        <div className="profil-info">
          <div className="info-section">
            {isEditing ? (
              <input
                type="text"
                name="prenom"
                value={userData.prenom}
                onChange={handleInputChange}
                className="edit-input"
              />
            ) : (
              <h2>{userData.prenom}</h2>
            )}
            
            {isEditing ? (
              <input
                type="text"
                name="nom"
                value={userData.nom}
                onChange={handleInputChange}
                className="edit-input"
              />
            ) : (
              <h3>{userData.nom}</h3>
            )}
          </div>

          <div className="info-grid">
            <div className="info-item">
              <span className="label">Page:</span>
              {isEditing ? (
                <input
                  type="text"
                  name="page"
                  value={userData.page}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.page}</span>
              )}
            </div>

            <div className="info-item">
              <span className="label">Contributions:</span>
              {isEditing ? (
                <input
                  type="text"
                  name="contributions"
                  value={userData.contributions}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.contributions}</span>
              )}
            </div>

            <div className="info-item">
              <span className="label">Module:</span>
              {isEditing ? (
                <input
                  type="text"
                  name="module"
                  value={userData.module}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.module}</span>
              )}
            </div>

            <div className="info-item">
              <span className="label">Projets:</span>
              {isEditing ? (
                <input
                  type="text"
                  name="projets"
                  value={userData.projets}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.projets}</span>
              )}
            </div>

            <div className="info-item">
              <span className="label">Compétences:</span>
              {isEditing ? (
                <input
                  type="text"
                  name="competences"
                  value={userData.competences}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.competences}</span>
              )}
            </div>

            <div className="info-item">
              <span className="label">Disponibilités:</span>
              {isEditing ? (
                <input
                  type="text"
                  name="disponibilites"
                  value={userData.disponibilites}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.disponibilites}</span>
              )}
            </div>
          </div>
        </div>

        <div className="disponibilites-section">
          <h3>Disponibilités</h3>
          <div className="date-range">
            <div className="date-input">
              <span>Du:</span>
              {isEditing ? (
                <input
                  type="date"
                  name="disponibiliteDebut"
                  value={userData.disponibiliteDebut}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.disponibiliteDebut || "01/01/2023"}</span>
              )}
            </div>
            <div className="date-input">
              <span>Au:</span>
              {isEditing ? (
                <input
                  type="date"
                  name="disponibiliteFin"
                  value={userData.disponibiliteFin}
                  onChange={handleInputChange}
                  className="edit-input"
                />
              ) : (
                <span>{userData.disponibiliteFin || "31/12/2023"}</span>
              )}
            </div>
          </div>
          <div className="view-buttons">
            <button className="view-button">Voir</button>
            <button className="view-button">Voir</button>
          </div>
        </div>

        <div className="actions-section">
          <button className="action-button">Page Modification Profil</button>
          <button className="action-button">Ajouter à vos contacts</button>
        </div>
      </div>

      {isEditing && (
        <div className="save-section">
          <button className="save-button" onClick={handleSave}>
            Enregistrer les modifications
          </button>
        </div>
      )}
    </div>
  );
};

export default Profil;