import React, { useEffect, useState } from 'react';

const UserProfile = () => {
  const [user, setUser] = useState(null); // Pour stocker les infos utilisateur
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    // 1. Récupérer le token (par exemple depuis localStorage)
    const token = localStorage.getItem('apiToken'); // ou autre méthode de stockage

    // 2. Faire l'appel API avec le token
    fetch('http://127.0.0.1:8000/api/me', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    })
      .then(response => {
        if (!response.ok) {
          throw new Error('Non autorisé');
        }
        return response.json();
      })
      .then(data => {
        setUser(data[0]); // car tu reçois un tableau
      })
      .catch(error => {
        console.error('Erreur lors de la récupération de l’utilisateur :', error);
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) return <p>Chargement...</p>;
  if (!user) return <p>Aucun utilisateur trouvé.</p>;

  return (
    <div>
      <h2>Bienvenue, {user.prenom} {user.nom}</h2>
      <p>Email : {user.courriel}</p>
      <p>Téléphone : {user.telephone}</p>
      <p>Projet : {user.projetParticipe || 'Aucun'}</p>
      <p>Rôle : {user.roles.join(', ')}</p>
    </div>
  );
};

export default UserProfile;
