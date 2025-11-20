import { useNavigate } from 'react-router-dom';
import userPhoto from '../images/users/user1.png';
import styles from '../style/Home.module.css';
import React, { useEffect, useState } from 'react';
import Projects from './Projects';

const API_URL = 'https://openhub-back.onrender.com;


const Home = () => {
  const [user, setUser] = useState({});
  const [skills, setSkills] = useState([]);
  const [projects, setProjects] = useState([]);
  const [userprojects, setUserProjects] = useState([]);
  const [availableSkills, setAvailableSkills] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [selectedSkill, setSelectedSkill] = useState(null);
  const [SelectedProject, setSelectedProject] = useState(null);
  const [newSkillId, setNewSkillId] = useState('');
  const [newProjectId, setNewProjectId] = useState('');
  const [message, setMessage] = useState('');
  const [availabilityStart, setAvailabilityStart] = useState('');
  const [availabilityEnd, setAvailabilityEnd] = useState('');


  const openSkillModal = (skill) => {
    setSelectedSkill(skill);
    setIsModalOpen(true);
  };
  const openProjectModal = (project) => {
    setSelectedProject(project);
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

      const response = await fetch(`${API_URL}/api/user/availability`, {
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


  const addProject = async () => {
    try {
      const token = localStorage.getItem("token");

      const response = await fetch(`${API_URL}/api/user/add/project`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({
          project_id: parseInt(newProjectId)
        }),
      });
      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.project_name} added successfully`);
        setNewProjectId('');
        // Recharger les compétences
        await fetchUserProjects();

      }
    } catch (error) {
      setMessage('❌ Erreur réseau lors de l\'ajout');
      console.error('Error adding project:', error);
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

      const response = await fetch(`${API_URL}/api/user/add/skills`, {
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

      const response = await fetch(`${API_URL}/api/skills`, {
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
  const fetchUserProjects = async () => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(`${API_URL}/api/user/projects`, {
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


  // Fonction pour récupérer les compétences
  const fetchAllProjects = async () => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(`${API_URL}/api/allprojects`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      if (!response.ok) {
        throw new Error(`Skills API error: ${response.status}`);
      }

      const dataAllProject = await response.json();
      setProjects(Array.isArray(dataAllProject) ? dataAllProject : []);
    } catch (error) {
      console.error('Error fetching skills:', error);
    }
  };

  const fetchSkills = async () => {
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(`${API_URL}/api/user/skills`, {
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
    console.log('Token:', token)

    try {
      // Récupérer l'utilisateur
      const userResponse = await fetch(`${API_URL}/api/getConnectedUser`, {
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


  const removeProject = async (projectId) => {
    if (!window.confirm('Êtes-vous sûr de vouloir vous retirer de ce projet ?')) {
      return;
    }

    try {
      setMessage('⏳ Removing project...');
      const token = localStorage.getItem("token");

      const response = await fetch(`${API_URL}/api/user/delete/project`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({
          project_id: projectId
        }),
      });

      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.project_name} removed successfully`);
        await fetchUserProjects();
        closeModal();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (error) {
      setMessage('❌ Erreur réseau lors de la suppression');
      console.error('Error removing project:', error);
    }
  };


  const removeSkill = async (skillId) => {
    if (!window.confirm('Êtes-vous sûr de vouloir supprimer cette compétence ?')) {
      return;
    }

    try {
      setMessage('⏳ Removing skill...');
      const token = localStorage.getItem("token");

      const response = await fetch(`${API_URL}/api/user/delete/skill`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({
          skill_id: skillId
        }),
      });

      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.skill_name} removed successfully`);
        await fetchSkills();
        closeModal();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (error) {
      setMessage('❌ Erreur réseau lors de la suppression');
      console.error('Error removing skill:', error);
    }
  };


  useEffect(() => {
    fetchData();
    fetchAvailableSkills();
    fetchAllProjects();
    fetchUserProjects();
  }, []);



  if (loading) return <p>Loading user data...</p>;
  if (error) return <p>Error: {error}</p>;








  return (
    <div className={styles.profileContainer}>
      <div className={styles.profileHeader}>
        <img src={userPhoto} alt="Profile photo" className={styles.profilePhoto} />

        <h2>{user.lastName || "Last name"}</h2>
        <h2>{user.firstName || "First name"}</h2>
      </div>

      <div className={styles.profileContent}>
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
                  {skill.name}
                </button>

      



                

                
              ))
            ) : (
              <span>Aucune compétence</span>
            )}
          </div>
          <div className={styles.dateRange}>
            <span>Projects countributed : ({userprojects.length}) </span>
            {userprojects.length > 0 ? (
              userprojects.map(project => (

                <button
                  key={project.id}
                  className={styles.skillButton}
                  onClick={() => openProjectModal(project)}
                >
                  {project.name}
                </button>
              ))
            ) : (
              <span>No projects contributed</span>
            )}
          </div>
        </div>

        {message && (
          <div
            className={`${styles.messageBox} ${message.includes("✅")
              ? styles.success
              : message.includes("⏳")
                ? styles.info
                : styles.error
              }`}
          >
            {message}
          </div>
        )}


        <h2 className={styles.addSkillTitle}>Add a New Skill</h2>



        <div className={styles.divider}>

          <div className={styles.addSkillContainer}>
            
            <div className={styles.addSkillCard}>
              <div className={styles.addSkillForm}>
                
                <h2 className={styles.addSkillTitle}>Add you a new skill</h2>
                <div className={styles.formGroup}>
                  <label htmlFor="skillSelect" className={styles.formLabel}>
                    Select a skill
                  </label>
                  <select
                    id="skillSelect"
                    value={newSkillId}
                    onChange={(e) => setNewSkillId(e.target.value)}
                    className={styles.formSelect}
                    disabled={loading}
                  >
                    <option value="">-- Choose a skill --</option>
                    {availableSkills.map(skill => (
                      <option key={skill.id} value={skill.id}>
                        {skill.name}
                      </option>
                    ))}
                  </select>
                </div>

                <button
                  onClick={addSkill}
                  className={styles.btnPrimary}
                  disabled={loading || !newSkillId}
                >
                  {loading ? 'Adding...' : 'Add Skill'}
                </button>
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
                    value={availabilityStart || ""}
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
                    value={availabilityEnd || ""}
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


              </div>
            </div>
          </div>


          <div className={styles.addSkillContainer}>
            <div className={styles.addSkillCard}>
              <h2 className={styles.addSkillTitle}>Contribuer a d'autres Projets ({projects.length})</h2>
              <div className={styles.addSkillForm}>
                <h2>All Projects ({projects.length})</h2>

                <select
                  id="skillSelect"
                  value={newProjectId}
                  onChange={(e) => setNewProjectId(e.target.value)}
                  className={styles.formSelect}
                >
                  <option value="">-- Choose a project --</option>
                  {projects.map(project => (
                    <option key={project.id} value={project.id}>
                      {project.name}
                    </option>
                  ))}
                </select>

                <button
                  onClick={addProject}
                  className={styles.btnPrimary}
                >
                  Add Project
                </button>



              </div>
            </div>

          </div>




          {/* Modal des détails de compétence */}
          {isModalOpen && SelectedProject && (
            <div className={styles.modalOverlay}>
              <div className={styles.modalContent}>
                <button className={styles.closeButton} onClick={closeModal}>×</button>
                <h2 className={styles.modalTitle}>{SelectedProject.name}</h2>
                <div className={styles.modalInfo}>
                  <div>
                    <h3>Description</h3>
                    <p>{SelectedProject.description || "Non renseigné"}</p>
                  </div>
                  <div>
                    <h3>Technologies utilisées</h3>
                    <p>{SelectedProject.requiredSkills || "Non renseigné"}</p>
                  </div>
                  <div>
                    <h3>Durée</h3>
                    <p>From {SelectedProject.startDate ? new Date(SelectedProject.startDate).toLocaleDateString() : "N/A"} to {SelectedProject.endDate ? new Date(SelectedProject.endDate).toLocaleDateString() : "N/A"}</p>
                  </div>


                </div>

                                <button
                  onClick={() => removeProject(SelectedProject.id)}
                  className={styles.btnDanger}
                  style={{ marginTop: '20px' }}
                >
                  🗑️ Remove from this project
                </button>
              </div>
            </div>
          )}


          {isModalOpen && selectedSkill && (
            <div className={styles.modalOverlay}>
              <div className={styles.modalContent}>
                <button className={styles.closeButton} onClick={closeModal}>×</button>
                <h2 className={styles.modalTitle}>{selectedSkill.name}</h2>
                <div className={styles.modalInfo}>
                  <div>
                    <h3>Description</h3>
                    <p>{selectedSkill.description || "Non renseigné"}</p>
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
                                <button
                  onClick={() => removeSkill(selectedSkill.id)}
                  className={styles.btnDanger}
                  style={{ marginTop: '20px' }}
                >
                  🗑️ Remove this skill
                </button>
              </div>
            </div>
          )}

        </div>
      </div>
    </div>
  );
};

export default Home;