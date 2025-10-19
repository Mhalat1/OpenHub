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



  useEffect(() => {
    fetchData();
    fetchSkills();
    fetchUserProjects();
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
          <div className={styles.searchIcon}>🔍</div>
          <input
            type="text"
            placeholder="Search users"
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
            className={`${styles.navButton} ${activeTab === 'projects' ? styles.active : ''}`}
            onClick={() => setActiveTab('projects')}
          >
            Projects
          </button>
          <button 
            className={`${styles.navButton} ${activeTab === 'users' ? styles.active : ''}`}
            onClick={() => setActiveTab('users')}
          >
            Users
          </button>
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
          Invitations (2)
        </button>
      </div>

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
              <img 
                src={user.avatar || `https://ui-avatars.com/api/?name=${user.firstName}+${user.lastName}&background=random`} 
                alt={`${user.firstName} ${user.lastName}`}
                onError={(e) => {
                  e.target.onerror = null;
                  e.target.src = `https://ui-avatars.com/api/?name=${user.firstName}+${user.lastName}&background=4F46E5`;
                }}
              />
            </div>
            <div className={styles.userInfo}>
              <h3 className={styles.userName}>{user.firstName} {user.lastName}</h3>
            </div>
            <button 
              className={styles.addButton}
              onClick={(e) => handleAddUser(user.id, e)}
              aria-label={`Add ${user.firstName} ${user.lastName}`}
            >
              ✕
            </button>
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
                        <li key={} className={styles.skillItem}>
                          {skill}
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