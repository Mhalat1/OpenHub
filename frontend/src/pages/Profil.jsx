import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import utilisateurphoto from '../images/utilisateurs/user1.png';
import styles from '../style/Profil.module.css';

const Profil = () => {
  const [estModification, setEstModification] = useState(false);
  const [estModaleOuverte, setEstModaleOuverte] = useState(false);  // Nouvel état pour la modale
  const [donneesUtilisateur, setDonneesUtilisateur] = useState({
    prenom: "Jean",
    nom: "Dupont",
    page: "Designer UX/UI",
    email: "email@projets",
    telephone: "0606060606",
    compétences: "react, node.js, figma",
    disponibiliteDebut: "",
    disponibiliteFin: "",
    contributions: [
      {
        "projet": "Formatech",
        "technologies": [
          "JavaScript",
          "React",
          "Node.js",
          "MongoDB"
        ],
        "contributions": [
          "Développement d'une application de gestion de tâches",
          "Intégration de l'API RESTful pour la gestion des utilisateurs",
          "Optimisation des performances du front-end avec React"
        ],
        "periode": "Janvier 2023 - Juin 2023"
      },
      {
        "projet": "OpenClassrooms",
        "technologies": [
          "HTML",
          "CSS",
          "JavaScript",
          "React",
          "Node.js"
        ],
        "contributions": [
          "Création de la maquette d'une interface utilisateur responsive",
          "Développement d'un module de quiz interactif",
          "Participation à des revues de code et mentorat des étudiants"
        ],
        "periode": "Mars 2022 - Décembre 2022"
      },
      {
        "projet": "FreeCodeCamp",
        "technologies": [
          "JavaScript",
          "Node.js",
          "Express",
          "MongoDB"
        ],
        "contributions": [
          "Conception et implémentation de solutions pour les exercices backend",
          "Révision des pull requests et amélioration de la documentation",
          "Participation au développement d'une nouvelle fonctionnalité pour les défis de programmation"
        ],
        "periode": "Août 2021 - Présent"
      }
    ]
  });

  const navigate = useNavigate();

  const handleChangementInput = (e) => {
    const { name, value } = e.target;
    setDonneesUtilisateur(prevState => ({
      ...prevState,
      [name]: value
    }));
  };

  const handleSauvegarder = () => {
    setEstModification(false);
    // Ajouter la logique pour sauvegarder les données si nécessaire
  };

  const toggleModale = () => {
    setEstModaleOuverte(!estModaleOuverte);  // Permet d'ouvrir ou fermer la modale
  };

  return (
    <div className={styles.profilConteneur}>
      <div className={styles.profilEnTête}>
        <img src={utilisateurphoto} alt="Photo de profil" className={styles.profilPhoto} />
        <h1>Niveau 7</h1>
      </div>

      <div className={styles.profilContenu}>
        <div className={styles.profilInfos}>
          <div className={styles.sectionInfos}>
            <h2>{donneesUtilisateur.prenom}</h2>
            <h2>{donneesUtilisateur.nom}</h2>
          </div>
        </div>

        <div className={styles.sectionDisponibilités}>
          <button 
            className={styles.boutonModifier}
            onClick={() => setEstModification(!estModification)}
          >
            {estModification ? 'Annuler' : 'Modifier'}
          </button>
          <div className={styles.plageDates}>
            <div className={styles.inputDate}>
              <span>Email:</span>
              {estModification ? (
                <input
                  type="text"
                  name="email"
                  value={donneesUtilisateur.email}
                  onChange={handleChangementInput}
                  className={styles.champModifier}
                />
              ) : (
                <span>{donneesUtilisateur.email || "test@test"}</span>
              )}
            </div>
            <div className={styles.inputDate}>
              <span>Téléphone:</span>
              {estModification ? (
                <input
                  type="number"
                  name="telephone"
                  value={donneesUtilisateur.telephone}
                  onChange={handleChangementInput}
                  className={styles.champModifier}
                />
              ) : (
                <span>{donneesUtilisateur.telephone || "0663254125"}</span>
              )}
            </div>
            <div className={styles.inputDate}>
              <span>Disponible du:</span>
              {estModification ? (
                <input
                  type="date"
                  name="disponibiliteDebut"
                  value={donneesUtilisateur.disponibiliteDebut}
                  onChange={handleChangementInput}
                  className={styles.champModifier}
                />
              ) : (
                <span>{donneesUtilisateur.disponibiliteDebut || "01/01/2023"}</span>
              )}
            </div>
            <div className={styles.inputDate}>
              <span>Au:</span>
              {estModification ? (
                <input
                  type="date"
                  name="disponibiliteFin"
                  value={donneesUtilisateur.disponibiliteFin}
                  onChange={handleChangementInput}
                  className={styles.champModifier}
                />
              ) : (
                <span>{donneesUtilisateur.disponibiliteFin || "31/12/2023"}</span>
              )}
            </div>
            <div className={styles.inputDate}>
              <span>Compétences:</span>
              {estModification ? (
                <input
                  type="text"
                  name="compétences"
                  value={donneesUtilisateur.compétences}
                  onChange={handleChangementInput}
                  className={styles.champModifier}
                />
              ) : (
                <span>{donneesUtilisateur.compétences || ""}</span>
              )}
            </div>
          </div>

          {estModification && (
            <div className={styles.sectionSauvegarde}>
              <button className={styles.boutonSauvegarde} onClick={handleSauvegarder}>
                Enregistrer les modifications
              </button>
            </div>
          )}
        </div>

        {/* Bouton pour ouvrir la modale */}
        <button 
          className={styles.boutonAction} 
          onClick={toggleModale}>
          Modale Contributions
        </button>

        {/* Modale des contributions */}
        {estModaleOuverte && (
          <div className={styles.modale}>
            <div className={styles.contenuModale}>
              <h2>Mes Contributions</h2>
              <button className={styles.boutonFermer} onClick={toggleModale}>X</button>
              <ul>
                {donneesUtilisateur.contributions.map((contribution, index) => (
                  <li key={index} className={styles.itemContribution}>
                    <h3>{contribution.projet}</h3>
                    <p><strong>Technologies:</strong> {contribution.technologies.join(", ")}</p>
                    <p><strong>Contributions:</strong></p>
                    <ul>
                      {contribution.contributions.map((detailContribution, i) => (
                        <li key={i}>{detailContribution}</li>
                      ))}
                    </ul>
                    <p><strong>Période:</strong> {contribution.periode}</p>
                  </li>
                ))}
              </ul>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default Profil;
