import { useNavigate } from 'react-router-dom';
import userPhoto from '../images/users/user1.png';
import styles from '../style/Home.module.css';
import React, { useEffect, useState } from 'react';
import Projects from './Projects';

const API_URL = import.meta.env.VITE_API_URL;

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
  const [addingSkill, setAddingSkill] = useState(false);
  const [addingProject, setAddingProject] = useState(false);
  const [updatingAvailability, setUpdatingAvailability] = useState(false);

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
    setSelectedProject(null);
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
      setUpdatingAvailability(true);
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
        await fetchData();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (error) {
      console.error("Error adding availability:", error);
      setMessage("❌ Network error while updating availability");
    } finally {
      setUpdatingAvailability(false);
    }
  };

  const addProject = async () => {
    if (!newProjectId) {
      setMessage('❌ Please select a project');
      return;
    }

    try {
      setAddingProject(true);
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
        await fetchUserProjects();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (error) {
      setMessage('❌ Network error while adding project');
      console.error('Error adding project:', error);
    } finally {
      setAddingProject(false);
    }
  };

  const addSkill = async () => {
    if (!newSkillId) {
      setMessage('❌ Please select a skill');
      return;
    }

    try {
      setAddingSkill(true);
      setMessage('⏳ Adding skill...');
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
        await fetchSkills();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (error) {
      setMessage('❌ Network error while adding skill');
      console.error('Error adding skill:', error);
    } finally {
      setAddingSkill(false);
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
        throw new Error(`Skills API error: ${response.status}`);
      }

      const data = await response.json();
      setAvailableSkills(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error("Error fetching available skills:", error);
    }
  };

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
        throw new Error(`Projects API error: ${response.status}`);
      }

      const data = await response.json();
      setUserProjects(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching user projects:', error);
    }
  };

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
        throw new Error(`Projects API error: ${response.status}`);
      }

      const dataAllProject = await response.json();
      setProjects(Array.isArray(dataAllProject) ? dataAllProject : []);
    } catch (error) {
      console.error('Error fetching all projects:', error);
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
      await fetchSkills();

    } catch (error) {
      console.error('Error fetching user:', error);
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const removeProject = async (projectId) => {
    if (!window.confirm('Are you sure you want to leave this project?')) {
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
      setMessage('❌ Network error while removing project');
      console.error('Error removing project:', error);
    }
  };

  const removeSkill = async (skillId) => {
    if (!window.confirm('Are you sure you want to remove this skill?')) {
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
      setMessage('❌ Network error while removing skill');
      console.error('Error removing skill:', error);
    }
  };

  useEffect(() => {
    fetchData();
    fetchAvailableSkills();
    fetchAllProjects();
    fetchUserProjects();
  }, []);

  if (loading) return (
    <div className={styles.loadingContainer}>
      <div className={styles.spinner}></div>
      <p>Loading user data...</p>
    </div>
  );
  
  if (error) return (
    <div className={styles.errorContainer}>
      <p>Error: {error}</p>
    </div>
  );

  return (
    <div className={styles.profileContainer}>
      {/* Header Corrigé */}
      <div className={styles.profileHeader}>
        <div className={styles.headerContent}>
          <div className={styles.nameContainer}>
            <h2>{user.firstName || "First name"} {user.lastName || "Last name"}</h2>
            <p className={styles.userEmail}>{user.email || "..."}</p>
          </div>
        </div>
      </div>

      <div className={styles.profileContent}>
        {/* Section Informations */}
        <div className={styles.infoSection}>
          <h3 className={styles.sectionTitle}>📋 My Information</h3>
          <div className={styles.subsection}>
            <div className={styles.infoGrid}>
              <div className={styles.infoItem}>
                <span className={styles.infoLabel}>Available from</span>
                <span className={styles.infoValue}>
                  {user.availabilityStart ? new Date(user.availabilityStart).toLocaleDateString() : "Not set"}
                </span>
              </div>
              <div className={styles.infoItem}>
                <span className={styles.infoLabel}>to</span>
                <span className={styles.infoValue}>
                  {user.availabilityEnd ? new Date(user.availabilityEnd).toLocaleDateString() : "Not set"}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Section Compétences */}
        <div className={styles.infoSection}>
          <h3 className={styles.sectionTitle}>
            🛠️ My Skills ({skills.length})
          </h3>
          <div className={styles.subsection}>
            <div className={styles.skillsGrid}>
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
                <div className={styles.emptyState}>
                  No skills added yet
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Section Projets */}
        <div className={styles.infoSection}>
          <h3 className={styles.sectionTitle}>
            🚀 My Projects ({userprojects.length})
          </h3>
          <div className={styles.subsection}>
            <div className={styles.projectsGrid}>
              {userprojects.length > 0 ? (
                userprojects.map(project => (
                  <button
                    key={project.id}
                    className={styles.projectButton}
                    onClick={() => openProjectModal(project)}
                  >
                    {project.name}
                  </button>
                ))
              ) : (
                <div className={styles.emptyState}>
                  No projects yet
                </div>
              )}
            </div>
          </div>
        </div>

        {/* Messages */}
        {message && (
          <div className={`${styles.messageBox} ${
            message.includes("✅") ? styles.success : 
            message.includes("⏳") ? styles.info : 
            styles.error
          }`}>
            {message}
          </div>
        )}

        {/* Formulaires Réorganisés */}
        <div className={styles.divider}>
          {/* Gérer les compétences */}
          <div className={styles.formSection}>
            <h3 className={styles.sectionTitle}>🛠️ Manage Skills</h3>
            <div className={styles.subsection}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Add a skill</label>
                <select
                  value={newSkillId}
                  onChange={(e) => setNewSkillId(e.target.value)}
                  className={styles.formSelect}
                  disabled={addingSkill}
                >
                  <option value="">-- Choose a skill --</option>
                  {availableSkills.map(skill => (
                    <option key={skill.id} value={skill.id}>
                      {skill.name}
                    </option>
                  ))}
                </select>
                <button 
                  onClick={addSkill} 
                  className={styles.btnPrimary}
                  disabled={addingSkill || !newSkillId}
                >
                  {addingSkill ? (
                    <>
                      <span className={styles.spinner}></span>
                      Adding...
                    </>
                  ) : (
                    '+ Add Skill'
                  )}
                </button>
              </div>
            </div>
          </div>

          {/* Gérer les disponibilités */}
          <div className={styles.formSection}>
            <h3 className={styles.sectionTitle}>📅 My Availability</h3>
            <div className={styles.subsection}>
              <div className={styles.dateInputs}>
                <div className={styles.formGroup}>
                  <label className={styles.formLabel}>Start date</label>
                  <input
                    type="date"
                    value={availabilityStart}
                    onChange={(e) => setAvailabilityStart(e.target.value)}
                    className={styles.formInput}
                  />
                </div>
                <div className={styles.formGroup}>
                  <label className={styles.formLabel}>End date</label>
                  <input
                    type="date"
                    value={availabilityEnd}
                    onChange={(e) => setAvailabilityEnd(e.target.value)}
                    className={styles.formInput}
                  />
                </div>
              </div>
              <button 
                onClick={addAvailability} 
                className={styles.btnSecondary}
                disabled={updatingAvailability}
              >
                {updatingAvailability ? (
                  <>
                    <span className={styles.spinner}></span>
                    Updating...
                  </>
                ) : (
                  'Update Availability'
                )}
              </button>
            </div>
          </div>

          {/* Rejoindre un projet */}
          <div className={styles.formSection}>
            <h3 className={styles.sectionTitle}>🚀 Join a Project</h3>
            <div className={styles.subsection}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Select a project</label>
                <select
                  value={newProjectId}
                  onChange={(e) => setNewProjectId(e.target.value)}
                  className={styles.formSelect}
                  disabled={addingProject}
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
                  disabled={addingProject || !newProjectId}
                >
                  {addingProject ? (
                    <>
                      <span className={styles.spinner}></span>
                      Adding...
                    </>
                  ) : (
                    'Join Project'
                  )}
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Modal Projet */}
        {isModalOpen && SelectedProject && (
          <div className={styles.modalOverlay}>
            <div className={styles.modalContent}>
              <button className={styles.closeButton} onClick={closeModal}>×</button>
              <h2 className={styles.modalTitle}>{SelectedProject.name}</h2>
              <div className={styles.modalInfo}>
                <div>
                  <h3>Description</h3>
                  <p>{SelectedProject.description || "Not specified"}</p>
                </div>
                <div>
                  <h3>Technologies used</h3>
                  <p>{SelectedProject.requiredSkills || "Not specified"}</p>
                </div>
                <div>
                  <h3>Duration</h3>
                  <p>From {SelectedProject.startDate ? new Date(SelectedProject.startDate).toLocaleDateString() : "N/A"} to {SelectedProject.endDate ? new Date(SelectedProject.endDate).toLocaleDateString() : "N/A"}</p>
                </div>
              </div>
              <button
                onClick={() => removeProject(SelectedProject.id)}
                className={styles.btnDanger}
                style={{ marginTop: '20px' }}
              >
                🗑️ Leave this project
              </button>
            </div>
          </div>
        )}

        {/* Modal Compétence */}
        {isModalOpen && selectedSkill && (
          <div className={styles.modalOverlay}>
            <div className={styles.modalContent}>
              <button className={styles.closeButton} onClick={closeModal}>×</button>
              <h2 className={styles.modalTitle}>{selectedSkill.name}</h2>
              <div className={styles.modalInfo}>
                <div>
                  <h3>Description</h3>
                  <p>{selectedSkill.description || "Not specified"}</p>
                </div>
                <div>
                  <h3>Technologies used</h3>
                  <p>{selectedSkill.technoUtilisees || "Not specified"}</p>
                </div>
                <div>
                  <h3>Duration</h3>
                  <p>{selectedSkill.duree || "Not specified"}</p>
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
      <a href="/donate" className={styles['donate-button']}>💖 Faire un don</a>

    </div>
  );
};

export default Home;