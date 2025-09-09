import React, { useEffect, useState } from 'react';
import styles from '../style/Profil.module.css';

const Profil = () => {
  const [users, setUsers] = useState([]); // Store users
  const [loading, setLoading] = useState(true); // Loader state
  const [error, setError] = useState(null); // Error state
  const [activeTab, setActiveTab] = useState('public'); // Active tab
  const [searchTerm, setSearchTerm] = useState(''); // Search input

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
      setUsers(data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  // Filter users by name or email
  const filteredUsers = users.filter(user =>
    `${user.firstName} ${user.lastName}`.toLowerCase().includes(searchTerm.toLowerCase()) ||
    user.email.toLowerCase().includes(searchTerm.toLowerCase())
  );

  // Handle add user action
  const handleAddUser = (userId) => {
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
        {filteredUsers.map((user, index) => (
          <div key={user.id || index} className={styles.userCard}>
            <div className={styles.userAvatar}>
              <img 
                src={user.avatar || `https://i.pravatar.cc/80?img=${index + 1}`} 
                alt={`${user.firstName} ${user.lastName}`}
                onError={(e) => {
                  e.target.src = `https://ui-avatars.com/api/?name=${user.firstName}+${user.lastName}&background=random`;
                }}
              />
            </div>
            <div className={styles.userInfo}>
              <h3 className={styles.userName}>{user.firstName} {user.lastName}</h3>
              <p className={styles.userLevel}>Level {user.level || Math.floor(Math.random() * 50) + 1}</p>
            </div>
            <button 
              className={styles.addButton}
              onClick={() => handleAddUser(user.id)}
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
    </div>
  );
};

export default Profil;
