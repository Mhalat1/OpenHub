import React, { useEffect, useState } from 'react';
import styles from '../style/Profil.module.css';

const Profil = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('public');
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedUser, setSelectedUser] = useState(null);
  const [skills, setSkills] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [userProjects, setUserProjects] = useState([]);
  const [invitations, setInvitations] = useState([]);
  const [friends, setFriends] = useState([]);
  const [connectedUser, setUser] = useState([]);
  const [notification, setNotification] = useState({ message: "", type: "" });
  const [newSkillId, setNewSkillId] = useState('');


const [sentInvitations, setSentInvitations] = useState([]);
const [receivedInvitations, setReceivedInvitations] = useState([]);

console.log(sentInvitations);

  const fetchAllUsers = async () => {
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

      const uniqueUsers = Array.from(
        new Map(data.map(user => [user.id, user])).values()
      );

      setUsers(uniqueUsers);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };


  const fetchPendingInvitations = async () => {
    const token = localStorage.getItem("token");
    try {
      const res = await fetch("http://127.0.0.1:8000/api/invitations/pending", {
        headers: { "Authorization": `Bearer ${token}` },
      });
      const data = await res.json();
      setReceivedInvitations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching pending invitations:', error);
    }
  };

  const fetchReceivedInvitations = async () => {
    const token = localStorage.getItem("token");
    try {
      const res = await fetch("http://127.0.0.1:8000/api/invitations/received", {
        headers: { "Authorization": `Bearer ${token}` },
      });
      const data = await res.json();
      setReceivedInvitations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error("Error fetching received invitations:", error);
      setNotification({ message: "❌ Impossible de récupérer les invitations.", type: "error" });
    } finally {
      setLoading(false);
    }
  };

  
const fetchSentInvitations = async () => {
  const token = localStorage.getItem("token");
  try {
    const res = await fetch("http://127.0.0.1:8000/api/invitations/sent", {
      headers: { "Authorization": `Bearer ${token}` }
    });
    const data = await res.json();
    setSentInvitations(Array.isArray(data) ? data : []);
    console.log("sentInvitations :", data);
  } catch (error) {
    console.error('Error fetching sent invitations:', error);
  }
};


  const fetchUserFriends = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/user/friends", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });
      const data = await response.json();
      setFriends(data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const deleteFriend = async (friendId) => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(`http://127.0.0.1:8000/api/delete/friends/${friendId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      const data = await response.json();
      if (response.ok) {
        await fetchUserFriends();
        setNotification({ message: "✅ Ami supprimé avec succès !", type: "success" });
        setTimeout(() => setNotification({ message: "", type: "" }), 3000);
      } else {
        console.error("Erreur :", data.message);
      }
    } catch (error) {
      console.error("Erreur lors de la suppression :", error);
    }
  };

  const sendInvitation = async (friend_id) => {
    const token = localStorage.getItem("token");
    if (!token) return;

    try {
      const response = await fetch("http://127.0.0.1:8000/api/send/invitation", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({ friend_id }),
      });

      const data = await response.json();

      if (!response.ok) {
        setNotification({ message: data.message || "❌ Erreur lors de l'envoi de l'invitation.", type: "error" });
        setTimeout(() => setNotification({ message: "", type: "" }), 3000);
        return;
      }

      if (response.ok) {
        setNotification({ message: "✅ Invitation envoyée avec succès !", type: "success" });
        setTimeout(() => setNotification({ message: "", type: "" }), 3000);
        await fetchSentInvitations(); // Recharger les invitations envoyées
        
        
      }
    } catch (error) {
      setNotification({ message: "❌ Erreur réseau : impossible d'envoyer l'invitation.", type: "error" });
      console.error("Error adding friend:", error);
    }
  };

  const fetchConnectedUser = async () => {
    const token = localStorage.getItem("token");

    try {
      const userResponse = await fetch("http://127.0.0.1:8000/api/getConnectedUser", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      if (!userResponse.ok) {
        throw new Error(`User API error: ${userResponse.status}`);
      }

      const dataUser = await userResponse.json();
      setUser(dataUser);

    } catch (error) {
      console.error('Error fetching user:', error);
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };


  const deleteReceivedInvitation = async (senderId) => {
  const token = localStorage.getItem("token");
  if (!token) return;

  try {
    const response = await fetch(
      `http://127.0.0.1:8000/api/invitations/delete-received/${senderId}`,
      {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      }
    );

    const data = await response.json();

    if (response.ok && data.success) {
      setNotification({ message: "✅ Invitation supprimée avec succès.", type: "success" });
      // Rafraîchir la liste
      await fetchReceivedInvitations();
    } else {
      setNotification({ message: `❌ ${data.message || "Erreur lors de la suppression."}`, type: "error" });
    }
  } catch (error) {
    console.error("Erreur lors de la suppression :", error);
    setNotification({ message: "❌ Erreur réseau.", type: "error" });
  }

  setTimeout(() => setNotification({ message: "", type: "" }), 3000);
};


const deleteSentInvitation = async (receiverId) => {
  const token = localStorage.getItem("token");
  if (!token) return;

  try {
    const response = await fetch(
      `http://127.0.0.1:8000/api/invitations/delete-sent/${receiverId}`,
      {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      }
    );

    const data = await response.json();

    if (response.ok && data.success) {
      setNotification({ message: "✅ Invitation envoyée supprimée avec succès.", type: "success" });
      // Rafraîchir la liste
      await fetchSentInvitations();
    } else {
      setNotification({ message: `❌ ${data.message || "Erreur lors de la suppression."}`, type: "error" });
    }
  } catch (error) {
    console.error("Erreur lors de la suppression :", error);
    setNotification({ message: "❌ Erreur réseau.", type: "error" });
  }

  setTimeout(() => setNotification({ message: "", type: "" }), 3000);
};



  useEffect(() => {
    const init = async () => {
      await fetchConnectedUser();
      await fetchAllUsers();
      await fetchUserFriends();
      await fetchPendingInvitations();
    await fetchReceivedInvitations();
    await fetchSentInvitations();
    };
    init();
  }, []);

  const filteredUsers = users.filter(user => {
    const matchesSearch =
      `${user.firstName} ${user.lastName}`.toLowerCase().includes(searchTerm.toLowerCase()) ||
      user.email.toLowerCase().includes(searchTerm.toLowerCase());

    const isNotCurrentUser = connectedUser && user.id !== connectedUser.id;

    return matchesSearch && isNotCurrentUser;
  });

  const handleOpenModal = (user) => {
    setSelectedUser(user);
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSelectedUser(null);
  };

  if (loading) return <div className={styles.loading}>Loading...</div>;
  if (error) return <div className={styles.error}>Error: {error}</div>;













console.log(receivedInvitations);



  return (







    <div className={styles.profileContainer}>



      {/* Header with search bar */}
      <div className={styles.header}>
        <div className={styles.searchContainer}>
          <input
            type="text"
            placeholder="Search users"
            className={styles.searchInput}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
      </div>

      {/* Sub-navigation */}
      <div className={styles.subNav}>
        <button
          className={`${styles.subNavButton} ${activeTab === 'public' ? styles.active : ''}`}
          onClick={() => setActiveTab('public')}
        >
          Public
        </button>
        <button
          className={`${styles.subNavButton} ${activeTab === 'friends' ? styles.active : ''}`}
          onClick={() => setActiveTab('friends')}
        >
          Friends ({friends.length})
        </button>
        <button
          className={`${styles.subNavButton} ${activeTab === 'invitations' ? styles.active : ''}`}
          onClick={() => setActiveTab('invitations')}
        >
          invitations ({sentInvitations.length})
        </button>
        <button
          className={`${styles.subNavButton} ${activeTab === 'sent' ? styles.active : ''}`}
          onClick={() => setActiveTab('sent')}
        >
          Receved ({receivedInvitations.length})
        </button>
      </div>

      {activeTab === 'invitations' && (
        <div className={styles.invitationsContainer}>
      <h3 className={styles.invitationsTitle}>📨 Invitations Envoyee</h3>

      {notification.message && (
        <div className={notification.type === "success" ? styles.successMsg : styles.errorMsg}>
          {notification.message}
        </div>
      )}

      {sentInvitations.length === 0 ? (
        <p>Aucune invitation reçue</p>
      ) : (
        <div className={styles.invitationsGrid}>
          {sentInvitations.map(inv => (
            <div key={inv.id} className={styles.invitationsCard}>
              <div className={styles.invitationsInfo}>
                <h4>{inv.firstName} {inv.lastName}</h4>
                <p>{inv.email}</p>
              </div>
              <div className={styles.invitationsActions}>
                <button
                  className="bg-green-500 text-white px-2 py-1 rounded"
                  onClick={() => console.log("Accepter invitation de", inv.id)}
                >
                  Accepter
                </button>
<button
  className="bg-red-500 text-white px-2 py-1 rounded"
  onClick={() => deleteSentInvitation(inv.id)}
>
  Supprimer
</button>

              </div>
            </div>
          ))}
        </div>
      )}
    </div>
      )}

{activeTab === 'sent' && (
  <div className={styles.invitationsContainer}>
    <h3 className={styles.invitationsTitle}>📥 Invitations Reçues</h3>

    {notification.message && (
      <div className={notification.type === "success" ? styles.successMsg : styles.errorMsg}>
        {notification.message}
      </div>
    )}

    {receivedInvitations.length === 0 ? (
      <div className={styles.noInvitations}>
        <p>Aucune invitation reçue</p>
      </div>
    ) : (
      <div className={styles.invitationsGrid}>
        {receivedInvitations.map((inv) => (
          <div key={inv.id} className={styles.invitationsCard}>
            <div className={styles.invitationsInfo}>
              <h4>{inv.firstName} {inv.lastName}</h4>
              <p>{inv.email}</p>
            </div>

            <div className={styles.invitationsActions}>
              <button
                className="bg-green-500 text-white px-2 py-1 rounded"
                onClick={() => console.log("Accepter invitation de", inv.id)}
              >
                Accepter
              </button>
              <button
                className="bg-red-500 text-white px-2 py-1 rounded"
                onClick={() => deleteReceivedInvitation(inv.id)}
              >
                Supprimer
              </button>
            </div>
          </div>
        ))}
      </div>
    )}
  </div>
)}

      {/* Section des amis */}
      {activeTab === 'friends' && (
        <div className={styles.friendsContainer}>
          <h3 className={styles.friendsTitle}>👥 Liste d'amis</h3>

          {friends.length === 0 ? (
            <div className={styles.noFriends}>
              <p>Aucun ami ajouté.</p>
            </div>
          ) : (
            <div className={styles.friendsGrid}>
              {friends.map((friend) => (
                <div key={friend.id} className={styles.friendCard}>
                  <div className={styles.friendInfo}>
                    <h4 className={styles.friendName}>
                      {friend.firstName} {friend.lastName}
                    </h4>
                    <p className={styles.friendEmail}>{friend.email}</p>
                  </div>

                  <div className={styles.friendActions}>
                    <button
                      className={styles.unfriendBtn}
                      onClick={() => deleteFriend(friend.id)}
                    >
                      ❌ Unfriend
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Notifications */}
      {notification.message && (
        <div
          className={notification.type === "success" ? styles.successMsg : styles.errorMsg}
        >
          {notification.message}
        </div>
      )}

      {/* Users grid */}
      {activeTab === 'public' && (
        <div className={styles.usersGrid}>
          {filteredUsers.map((user) => (
            <div
              key={user.id}
              className={styles.userCard}
              onClick={() => handleOpenModal(user)}
              style={{ cursor: 'pointer' }}
            >
              <div className={styles.userInfo}>
                <h3 className={styles.userName}>{user.firstName} {user.lastName}</h3>
              </div>
            </div>
          ))}
        </div>
      )}

      {filteredUsers.length === 0 && !loading && activeTab === 'public' && (
        <div className={styles.noResults}>
          No users found
        </div>
      )}

      {/* Modal */}
      {isModalOpen && selectedUser && (
        <div className={styles.modalOverlay} onClick={handleCloseModal}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.modalClose} onClick={handleCloseModal}>
              ✕
            </button>

            <div className={styles.modalHeader}>
              <h2 className={styles.modalTitle}>
                {selectedUser.firstName} {selectedUser.lastName}
              </h2>
            </div>

            <div className={styles.modalBody}>
              <div className={styles.modalInfo}>
                <div className={styles.infoRow}>
                  <span className={styles.infoLabel}>📧 Email:</span>
                  <span className={styles.infoValue}>{selectedUser.email}</span>
                </div>

                {selectedUser.availabilityStart && (
                  <div className={styles.infoRow}>
                    <span className={styles.infoLabel}>📱 availabilityStart:</span>
                    <span className={styles.infoValue}>{selectedUser.availabilityStart}</span>
                  </div>
                )}

                {selectedUser.availabilityEnd && (
                  <div className={styles.infoRow}>
                    <span className={styles.infoLabel}>💬 availabilityEnd:</span>
                    <span className={styles.infoValue}>{selectedUser.availabilityEnd}</span>
                  </div>
                )}

                {userProjects.length > 0 && (
                  <div className={styles.infoRow}>
                    <span className={styles.infoLabel}>📁 Projects:</span>
                    <ul className={styles.projectList}>
                      {userProjects.map(project => (
                        <li key={project.id} className={styles.projectItem}>
                          {project.name}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}

                {skills.length > 0 && (
                  <div className={styles.infoRow}>
                    <span className={styles.infoLabel}>🛠️ Skills:</span>
                    <ul className={styles.skillList}>
                      {skills.map((skill) => (
                        <li key={skill.id} className={styles.skillItem}>
                          {skill.name}
                        </li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>

              <div className={styles.modalActions}>
                <button
                  className={styles.modalButton}
                  onClick={(e) => {
                    e.stopPropagation();
                    sendInvitation(selectedUser.id);
                    handleCloseModal();
                  }}
                >
                  Add Friend
                </button>
                <button
                  className={styles.modalButtonSecondary}
                  onClick={handleCloseModal}
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Profil;