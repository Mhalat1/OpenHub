import { useNavigate } from 'react-router-dom';
import utilisateurphoto from '../images/utilisateurs/user1.png';
import styles from '../style/Accueil.module.css';
import React, { useEffect, useState } from 'react';

const Accueil = () => {
  const [user, setUser] = useState({}); // ✅ objet vide au départ
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('public');
  const [searchTerm, setSearchTerm] = useState('');
  const [estModaleOuverte, setEstModaleOuverte] = useState(false);

  const fetchData = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/getConnectedUser", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });
      const data = await response.json();
      setUser(data); // ✅ l’API écrase l’état avec l’utilisateur connecté
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const toggleModale = () => {
    setEstModaleOuverte(!estModaleOuverte);
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
            <h2>{user.nom || "Nom"}</h2>
            <h2>{user.prenom || "Prénom"}</h2>
          </div>
        </div>

        <div className={styles.sectionDisponibilités}>
          <div className={styles.plageDates}>
            <div className={styles.inputDate}><span>Email: {user.email || "..."}</span></div>
          </div>
          <div className={styles.plageDates}>
            <div className={styles.inputDate}><span>Disponible du: {user.debutDispo || "..."}</span></div>
            <div className={styles.inputDate}><span>Au: {user.finDispo || "..."}</span></div>
          </div>
          <div className={styles.plageDates}>
            <div className={styles.inputDate}><span>Compétences: {user.competences || "..."}</span></div>
          </div>
        </div>

        <button className={styles.boutonAction} onClick={toggleModale}>
          Modale Contributions
        </button>

        {estModaleOuverte && (
          <div className={styles.modale}>
            <div className={styles.contenuModale}>
              <h2>Mes Contributions</h2>
              <button className={styles.boutonFermer} onClick={toggleModale}>X</button>

              <button className={styles.boutonAction} onClick={toggleModale}>
                Ajouter une nouvelle Contribution
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default Accueil;
