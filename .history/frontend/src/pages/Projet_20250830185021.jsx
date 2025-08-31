import { useEffect, useState } from 'react';

const UserProfile = () => {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const token = localStorage.getItem("token"); // Récupéré du stockage local

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
        setUser(data[0]); 
      })
      .catch(error => {
        console.error('Erreur utilisateur :', error);
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
