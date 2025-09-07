import React, { useEffect, useState } from 'react';
import styles from '../style/Projet.module.css';

const Projets = () => {
  const [projets, setProjets] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState(''); // état pour la recherche

  const fetchData = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/projet", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });
      if (!response.ok) throw new Error("Erreur lors de la récupération des projets");
      const data = await response.json();
      setProjets(data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  // Filtrage des projets selon le terme de recherche
  const filteredProjets = projets.filter(
    (projet) =>
      projet.nom.toLowerCase().includes(searchTerm.toLowerCase()) ||
      projet.competencesNecessaires.toLowerCase().includes(searchTerm.toLowerCase())
  );

  if (loading) return <p>Chargement des projets...</p>;
  if (error) return <p>Erreur : {error}</p>;

  return (
    <div>
      {/* Barre de recherche */}
      <input
        type="text"
        placeholder="Rechercher un projet ou une compétence..."
        value={searchTerm}
        onChange={(e) => setSearchTerm(e.target.value)}
        className={styles.searchInput}
      />

      <div className={styles.projetsContainer}>
        {filteredProjets.length === 0 ? (
          <p>Aucun projet trouvé.</p>
        ) : (
          filteredProjets.map((projet) => (
            <div key={projet.id} className={styles.projetCard}>
              <h2>{projet.nom}</h2>
              <p>{projet.description}</p>
              <p><strong>Compétences :</strong> {projet.competencesNecessaires}</p>
              <p><strong>Date de création :</strong> {new Date(projet.dateDeCreation).toLocaleDateString()}</p>
              <p><strong>Date de fin :</strong> {new Date(projet.dateDeFin).toLocaleDateString()}</p>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default Projets;
