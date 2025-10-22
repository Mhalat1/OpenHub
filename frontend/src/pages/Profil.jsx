import React, { useEffect, useState } from 'react';
import styles from '../style/Profil.module.css';

const Profil = () => {
  const [users, setUsers] = useState([]); // Store users
  const [loading, setLoading] = useState(true); // Loader state
  const [error, setError] = useState(null); // Error state
  const [activeTab, setActiveTab] = useState('public'); // Active tab
  const [searchTerm, setSearchTerm] = useState(''); // Search input
  const [selectedUser, setSelectedUser] = useState(null); // User for modal
  const [skills, setSkills] = useState([]); // Store skills
  const [isModalOpen, setIsModalOpen] = useState(false); // Modal state
  const [userProjects, setUserProjects] = useState([]); // Store user projects
  const [invitations, setInvitations] = useState([]); // Store pending invitations
  const [friends, setFriends] = useState([]); // Store friends list
  



  const rejectInvitations = async (invitationsId) => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/invitations/reject", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({ inviter_id: invitationsId }),
      });

      if (response.ok) {
        // Mettre à jour la liste des invitations après le refus
        setInvitations(prevInvitations => 
          prevInvitations.filter(inv => inv.id !== invitationsId)
        );
      } else {
        console.error('Failed to refuse invitations');
      }
    } catch (error) {
      console.error('Error refusing invitations:', error);
    }
  };

  const acceptInvitations = async (invitationsId) => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/invitations/accept", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({ inviter_id: invitationsId }),
      });

      if (response.ok) {
        // Mettre à jour la liste des invitations après l'acceptation
        setInvitations(prevInvitations => 
          prevInvitations.filter(inv => inv.id !== invitationsId)
        );
      } else {
        console.error('Failed to accept invitations');
      }
    } catch (error) {
      console.error('Error accepting invitations:', error);
    }
  };








  // Fetch users from API
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
      
      // Dédoublonner les utilisateurs par ID
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




  
  const fetchSkills = async () => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch("http://127.0.0.1:8000/api/user/skills", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error(`Skills API error: ${response.status}`);
      }

      const dataSkills = await response.json();
      setSkills(Array.isArray(dataSkills) ? dataSkills : []);
    } catch (error) {
      console.error('Error fetching skills:', error);
    }
  };

  const fetchPendingInvitations = async () => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch("http://127.0.0.1:8000/api/invitations/pending", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error(`Invitations API error: ${response.status}`);
      }

      const data = await response.json();
      setInvitations(data.success ? data.invitations : []);
      // Handle pending invitations data as needed
    } catch (error) {
      console.error('Error fetching pending invitations:', error);
    }
  };

  // Fonction pour récupérer les compétences
  const fetchUserProjects = async () => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch("http://127.0.0.1:8000/api/user/projects", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error(`Skills API error: ${response.status}`);
      }

      const data = await response.json();
      setUserProjects(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching skills:', error);
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



  useEffect(() => {
    fetchData();
    fetchSkills();
    fetchUserProjects();
    fetchPendingInvitations();
    fetchUserFriends();
  }, []);



  // Filter users by name or email
  const filteredUsers = users.filter(user =>
    `${user.firstName} ${user.lastName}`.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Open modal with user data
  const handleOpenModal = (user) => {
    setSelectedUser(user);
    setIsModalOpen(true);
  };

  // Close modal
  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSelectedUser(null);
  };

  // Handle add user action
  const handleAddUser = (userId, e) => {
    e.stopPropagation(); // Prevent modal from opening
    console.log(`Add user ${userId}`);
    // Add user logic here
  };

  if (loading) return <div className={styles.loading}>Loading...</div>;
  if (error) return <div className={styles.error}>Error: {error}</div>;

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
    Friends
  </button>
  <button 
    className={`${styles.subNavButton} ${activeTab === 'invitations' ? styles.active : ''}`}
    onClick={() => setActiveTab('invitations')}
  >
    Invitations ({invitations.length})
  </button>
</div>

{/* Section des invitations - affichage conditionnel */}
{activeTab === 'invitations' && (
  <div className={styles.invitationsContainer}>
    <h3 className={styles.invitationsTitle}>Invitations en attente</h3>
    
    {invitations.length === 0 ? (
      <div className={styles.noInvitations}>
        <p>Aucune invitations en attente</p>
      </div>
    ) : (
      <div className={styles.invitationsGrid}>
        {invitations.map((invitations) => (
          <div key={invitations.id} className={styles.invitationsCard}>
            <div className={styles.invitationsHeader}>
              <div className={styles.invitationsAvatar}>
              </div>
              <div className={styles.invitationsInfo}>
                <h4 className={styles.invitationsName}>
                  {invitations.firstName} {invitations.lastName}
                </h4>
                <p className={styles.invitationsEmail}>{invitations.email}</p>
              </div>
            </div>
            
            <div className={styles.invitationsActions}>
              <button 
                className={styles.acceptBtn}
                onClick={() => acceptInvitations(invitations.id)}
              >
                ✓ Accepter
              </button>
              <button 
                className={styles.rejectBtn}
                onClick={() => rejectInvitations(invitations.id)}
              >
                ✕ Refuser
              </button>
            </div>
          </div>
        ))}
      </div>
    )}
  </div>
)}

{activeTab == 'friends' && (
  <div className={styles.friendsContainer}>
    <h3 className={styles.friendsTitle}>Friends List</h3>
    {friends.length === 0 ? (
      <div className={styles.noFriends}>
        <p>No friends added yet.</p>
      </div>
    ) : (
      <div className={styles.friendsGrid}>
        {friends.map((friend) => (
          <div key={friend.id} className={styles.friendCard}>
            <div className={styles.friendAvatar}>
            </div>
            <div className={styles.friendInfo}>
              <h4 className={styles.friendName}>{friend.firstName} {friend.lastName}</h4>
              <p className={styles.friendEmail}>{friend.email}</p>
            </div>
          </div>
        ))}
      </div>
    )}
  </div>
)}





      {/* Users grid */}
      <div className={styles.usersGrid}>
        {filteredUsers.map((user) => (
          <div 
            key={user.id} 
            className={styles.userCard}
            onClick={() => handleOpenModal(user)}
            style={{ cursor: 'pointer' }}
          >
            <div className={styles.userAvatar}>
             
            </div>
            <div className={styles.userInfo}>
              <h3 className={styles.userName}>{user.firstName} {user.lastName}</h3>
            </div>
          </div>
        ))}
      </div>

      {filteredUsers.length === 0 && !loading && (
        <div className={styles.noResults}>
          No users found
        </div>
      )}

      {/* Modal */}
      {isModalOpen && selectedUser && skills && (
        <div className={styles.modalOverlay} onClick={handleCloseModal}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.modalClose} onClick={handleCloseModal}>
              ✕
            </button>
            
            <div className={styles.modalHeader}>
              <div className={styles.modalAvatar}>
                <img 
                  src={selectedUser.avatar || `https://ui-avatars.com/api/?name=${selectedUser.firstName}+${selectedUser.lastName}&background=random&size=120`} 
                  alt={`${selectedUser.firstName} ${selectedUser.lastName}`}
                />
              </div>
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
                  onClick={() => {
                    handleAddUser(selectedUser.id, { stopPropagation: () => {} });
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