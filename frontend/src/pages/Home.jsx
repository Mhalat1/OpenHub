import { useNavigate } from 'react-router-dom';
import userPhoto from '../images/users/user1.png';
import styles from '../style/Home.module.css';
import React, { useEffect, useState } from 'react';

const Home = () => {
  const [user, setUser] = useState({});
  const [skills, setSkills] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('public');
  const [searchTerm, setSearchTerm] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedSkill, setSelectedSkill] = useState(null);



  const openSkillModal = (skill) => {
    setSelectedSkill(skill);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setSelectedSkill(null);
    setIsModalOpen(false);
  };

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

      if (!response.ok) {
        throw new Error(`User API error: ${response.status}`);
      }

      const dataUser = await response.json();
      setUser(dataUser);
      console.log('User data:', dataUser);
    } catch (error) {
      console.error('Error fetching user:', error);
      setError(error.message);
    }

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
      console.log('Skills data:', dataSkills);
    } catch (error) {
      console.error('Error fetching skills:', error);
      // Don't set error here to avoid blocking the UI if skills fail
      // Just log it and keep skills as empty array
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  const toggleModal = () => {
    setIsModalOpen(!isModalOpen);
  };


  if (loading) return <p>Loading user data...</p>;
  if (error) return <p>Error: {error}</p>;

  return (
    <div className={styles.profileContainer}>
      <strong>Token:</strong>
      <p>{localStorage.getItem("token")}</p>

      <div className={styles.profileHeader}>
        <img src={userPhoto} alt="Profile photo" className={styles.profilePhoto} />
        <h1>Level 7</h1>
      </div>

      <div className={styles.profileContent}>
        <div className={styles.profileInfos}>
          <div className={styles.infoSection}>
            <h2>{user.lastName || "Last name"}</h2>
            <h2>{user.firstName || "First name"}</h2>
          </div>
        </div>

        <div className={styles.availabilitySection}>
          <div className={styles.dateRange}>
            <div className={styles.inputDate}>
              <span>Email: {user.email || "..."}</span>
            </div>
          </div>

          <div className={styles.dateRange}>
            <div className={styles.inputDate}>
              <span>Available from: {user.availabilityStart ? new Date(user.availabilityStart).toLocaleDateString() : "..."}</span>
            </div>
            <div className={styles.inputDate}>
              <span>To: {user.availabilityEnd ? new Date(user.availabilityEnd).toLocaleDateString() : "..."}</span>
            </div>
          </div>

          <div className={styles.dateRange}>
            <span>All Skills: </span>
            {skills.length > 0 ? (
              skills.map(skill => (
                <button
                  key={skill.id}
                  className={styles.skillButton}
                  onClick={() => openSkillModal(skill)}
                >
                  {skill.nom}
                </button>
              ))
            ) : (
              <span>No skills available</span>
            )}
          </div>


        </div>

       {isModalOpen && selectedSkill && (
  <div className={styles.modalOverlay}>
    <div className={styles.modalContent}>
      <button className={styles.closeButton} onClick={closeModal}>×</button>

      <h2 className={styles.modalTitle}>{selectedSkill.nom}</h2>

      <div className={styles.modalInfo}>
        <div>
          <h3>Contexte d’apprentissage</h3>
          <p>{selectedSkill.contextApprentissage || "Non renseigné"}</p>
        </div>

        <div>
          <h3>Technologies utilisées</h3>
          <p>{selectedSkill.technoUtilisees || "Non renseigné"}</p>
        </div>

        <div>
          <h3>Durée</h3>
          <p>{selectedSkill.duree || "Non renseigné"}</p>
        </div>
      </div>
    </div>
  </div>
)}

      </div>
    </div>
  );


};

export default Home;