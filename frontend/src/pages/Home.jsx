import { useNavigate } from 'react-router-dom';
import userPhoto from '../images/users/user1.png';
import styles from '../style/Home.module.css';
import React, { useEffect, useState } from 'react';

const Home = () => {
  const [user, setUser] = useState({});
  const [skills, setSkills] = useState([]);
  const [availableSkills, setAvailableSkills] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedSkill, setSelectedSkill] = useState(null);
  const [newSkillId, setNewSkillId] = useState('');
  const [message, setMessage] = useState('');
  const [availabilityStart, setAvailabilityStart] = useState('');
  const [availabilityEnd, setAvailabilityEnd] = useState('');


  const openSkillModal = (skill) => {
    setSelectedSkill(skill);
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setSelectedSkill(null);
    setIsModalOpen(false);
  };


const addAvailability = async () => {
  if (!availabilityStart) {
    setMessage('❌ Please enter start date');
    return;
  }
  if (!availabilityEnd) {
    setMessage('❌ Please enter end date');
    return;
  }

  try {
    setMessage('⏳ Updating availability...');
    const token = localStorage.getItem("token");

    const response = await fetch("http://127.0.0.1:8000/api/user/availability", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${token}`,
      },
      body: JSON.stringify({
        availabilityStart,
        availabilityEnd,
      }),
    });

    const result = await response.json();

    if (result.success) {
      setMessage("✅ Availability updated successfully!");
      setAvailabilityStart('');
      setAvailabilityEnd('');
      await fetchData(); // recharge user info
    } else {
      setMessage(`❌ ${result.message}`);
    }
  } catch (error) {
    console.error("Error adding availability:", error);
    setMessage("❌ Network error while updating availability");
  }
};




const addSkill = async () => {
    if (!newSkillId) {
      setMessage('❌ please enter a skill ID');
      return;
    }

    try {
      setMessage(' ⏳ Adding skill...');
      const token = localStorage.getItem("token");

      const response = await fetch("http://127.0.0.1:8000/api/user/skills/add", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({
          skill_id: parseInt(newSkillId)
        }),
      });

      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.skill_name} added successfully`);
        setNewSkillId('');
        // Recharger les compétences
        await fetchSkills();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (error) {
      setMessage('❌ Erreur réseau lors de l\'ajout');
      console.error('Error adding skill:', error);
    }
  };


  const fetchAvailableSkills = async () => {
    try {
      const token = localStorage.getItem("token");

      const response = await fetch("http://127.0.0.1:8000/api/skills", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`
        },
      });

      if (!response.ok) {
        throw new Error(`Erreur API compétences: ${response.status}`);
      }

      const data = await response.json();
      setAvailableSkills(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error("Erreur lors de la récupération des compétences:", error);
    }
  };


  // Fonction pour récupérer les compétences
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

  const fetchData = async () => {
    const token = localStorage.getItem("token");

    try {
      // Récupérer l'utilisateur
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

      // Récupérer les compétences
      await fetchSkills();

    } catch (error) {
      console.error('Error fetching user:', error);
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData();
    fetchAvailableSkills(); // 👈 Ajout ici
  }, []);



  if (loading) return <p>Loading user data...</p>;
  if (error) return <p>Error: {error}</p>;

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
            <span>Mes Compétences ({skills.length}) : </span>
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
              <span>Aucune compétence</span>
            )}
          </div>
        </div>




<div className={styles.addSkillContainer}>
  <div className={styles.addSkillCard}>
    <h2 className={styles.addSkillTitle}>Ajouter une compétence</h2>

    <div className={styles.addSkillForm}>
      <div className={styles.formGroup}>
        <label htmlFor="skillSelect" className={styles.formLabel}>
          Sélectionnez une compétence
        </label>
        <select
          id="skillSelect"
          value={newSkillId}
          onChange={(e) => setNewSkillId(e.target.value)}
          className={styles.formSelect}
        >
          <option value="">-- Choisir une compétence --</option>
          {availableSkills.map(skill => (
            <option key={skill.id} value={skill.id}>
              {skill.nom}
            </option>
          ))}
        </select>
      </div>

      <button
        onClick={addSkill}
        className={styles.btnPrimary}
      >
        Add Skill
      </button>

      {message && (
        <div
          className={`${styles.messageBox} ${
            message.includes("✅")
              ? styles.success
              : message.includes("⏳")
                ? styles.info
                : styles.error
          }`}
        >
          {message}
        </div>
      )}
    </div>
  </div>
</div>



<div className={styles.addSkillContainer}>
  <div className={styles.addSkillCard}>
    <h2 className={styles.addSkillTitle}>Modifier les dates de disponibilitees</h2>

    <div className={styles.addSkillForm}>
      <div className={styles.formGroup}>
        <label htmlFor="availabilityStart" className={styles.formLabel}>
          Date de debut de disponibilitee
        </label>
        <input
          type="date"
          id="availabilityStart"
          value={availabilityStart|| ""}
          onChange={(e) => setAvailabilityStart(e.target.value)}
          className={styles.formInput}
        />

      </div>
      <div className={styles.formGroup}>
        <label htmlFor="availabilityEnd" className={styles.formLabel}>
          Date de fin de disponibilitee
        </label>
        <input
          type="date"
          id="availabilityEnd"
          value={availabilityEnd|| ""}
          onChange={(e) => setAvailabilityEnd(e.target.value)}
          className={styles.formInput}
        />
      </div>

      <button
        onClick={addAvailability}
        className={styles.btnPrimary}
      >
        Add New Availability Dates
      </button>

      {message && (
        <div
          className={`${styles.messageBox} ${
            message.includes("✅")
              ? styles.success
              : message.includes("⏳")
                ? styles.info
                : styles.error
          }`}
        >
          {message}
        </div>
      )}
    </div>
  </div>
</div>




        {/* Modal des détails de compétence */}
        {isModalOpen && selectedSkill && (
          <div className={styles.modalOverlay}>
            <div className={styles.modalContent}>
              <button className={styles.closeButton} onClick={closeModal}>×</button>
              <h2 className={styles.modalTitle}>{selectedSkill.nom}</h2>
              <div className={styles.modalInfo}>
                <div>
                  <h3>Contexte d'apprentissage</h3>
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