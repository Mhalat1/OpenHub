import React, { useEffect, useState } from 'react';
import styles from '../style/Profil.module.css';

const Profil = () => {
  const [users, setUsers] = useState([]); // État pour stocker les utilisateurs
  const [loading, setLoading] = useState(true); // Pour afficher un loader si nécessaire
  const [error, setError] = useState(null); // Pour gérer les erreurs
  const [activeTab, setActiveTab] = useState('public'); // Pour gérer les onglets
  const [searchTerm, setSearchTerm] = useState(''); // Pour la barre de recherche

  // Fonction pour récupérer les utilisateurs
  const fetchData = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/getAllUsers", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });
      const data = await response.json();
      setUsers(data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  // Utiliser useEffect pour effectuer l'appel API lors du montage du composant
  useEffect(() => {
    fetchData();
  }, []);

  // Filtrer les utilisateurs selon le terme de recherche
  const filteredUsers = users.filter(user => 
    `${user.prenom} ${user.nom}`.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Fonction pour gérer l'ajout d'un utilisateur
  const handleAddUser = (userId) => {
    console.log(`Ajouter utilisateur ${userId}`);
    // Logique d'ajout ici
  };

  if (loading) return <div className={styles.loading}>Loading...</div>;
  if (error) return <div className={styles.error}>Error: {error}</div>;

  return (
    <div className={styles.profilContainer}>
      {/* Header avec barre de recherche */}
      <div className={styles.header}>
        <div className={styles.searchContainer}>
          <div className={styles.searchIcon}>🔍</div>
          <input
            type="text"
            placeholder="Search"
            className={styles.searchInput}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
      </div>

      {/* Navigation tabs */}
      <div className={styles.navTabs}>
        <div className={styles.navButtons}>
          <button 
            className={`${styles.navButton} ${activeTab === 'projets' ? styles.active : ''}`}
            onClick={() => setActiveTab('projets')}
          >
            Projets
          </button>
          <button 
            className={`${styles.navButton} ${activeTab === 'utilisateurs' ? styles.active : ''}`}
            onClick={() => setActiveTab('utilisateurs')}
          >
            Utilisateurs
          </button>
        </div>
      </div>

      {/* Sub navigation */}
      <div className={styles.subNav}>
        <button 
          className={`${styles.subNavButton} ${activeTab === 'public' ? styles.active : ''}`}
          onClick={() => setActiveTab('public')}
        >
          Public
        </button>
        <button 
          className={`${styles.subNavButton} ${activeTab === 'amis' ? styles.active : ''}`}
          onClick={() => setActiveTab('amis')}
        >
          Amis
        </button>
        <button 
          className={`${styles.subNavButton} ${activeTab === 'invitations' ? styles.active : ''}`}
          onClick={() => setActiveTab('invitations')}
        >
          Invitations (2)
        </button>
      </div>

      {/* Liste des utilisateurs */}
      <div className={styles.usersGrid}>
        {filteredUsers.map((user, index) => (
          <div key={user.id || index} className={styles.userCard}>
            <div className={styles.userAvatar}>
              <img 
                src={user.avatar || `https://i.pravatar.cc/80?img=${index + 1}`} 
                alt={`${user.prenom} ${user.nom}`}
                onError={(e) => {
                  e.target.src = `https://ui-avatars.com/api/?name=${user.prenom}+${user.nom}&background=random`;
                }}
              />
            </div>
            <div className={styles.userInfo}>
              <h3 className={styles.userName}>{user.prenom} {user.nom}</h3>
              <p className={styles.userLevel}>niveau {user.niveau || Math.floor(Math.random() * 50) + 1}</p>
            </div>
            <button 
              className={styles.addButton}
              onClick={() => handleAddUser(user.id)}
              aria-label={`Ajouter ${user.prenom} ${user.nom}`}
            >
              ✕
            </button>
          </div>
        ))}
      </div>

      {filteredUsers.length === 0 && !loading && (
        <div className={styles.noResults}>
          Aucun utilisateur trouvé
        </div>
      )}
    </div>
  );
};

export default Profil;