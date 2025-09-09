import { useNavigate } from 'react-router-dom';
import userPhoto from '../images/users/user1.png';
import styles from '../style/Home.module.css';
import React, { useEffect, useState } from 'react';

const Home = () => {
  const [user, setUser] = useState({});
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('public');
  const [searchTerm, setSearchTerm] = useState('');
  const [isModalOpen, setIsModalOpen] = useState(false);

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
      setUser(data);
    } catch (error) {
      setError(error.message);
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

  return (
    <div className={styles.profileContainer}>
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
            <div className={styles.inputDate}><span>Email: {user.email || "..."}</span></div>
          </div>
          <div className={styles.dateRange}>
            <div className={styles.inputDate}><span>Available from: {user.startAvailability || "..."}</span></div>
            <div className={styles.inputDate}><span>To: {user.endAvailability || "..."}</span></div>
          </div>
          <div className={styles.dateRange}>
            <div className={styles.inputDate}><span>Skills: {user.skills || "..."}</span></div>
          </div>
        </div>

        <button className={styles.actionButton} onClick={toggleModal}>
          Contributions Modal
        </button>

        {isModalOpen && (
          <div className={styles.modal}>
            <div className={styles.modalContent}>
              <h2>My Contributions</h2>
              <button className={styles.closeButton} onClick={toggleModal}>X</button>

              <button className={styles.actionButton} onClick={toggleModal}>
                Add a new Contribution
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default Home;
