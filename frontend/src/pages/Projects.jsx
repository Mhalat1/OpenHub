import React, { useEffect, useState } from 'react';
import styles from '../style/Projects.module.css';

const Projects = () => {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [message, setMessage] = useState('');
  
  const [userProjects, setUserProjects] = useState([]);
  const [availableSkills, setAvailableSkills] = useState([]);
  
  // States pour les projets
  const [project, setProject] = useState({
    name: '',
    description: '',
    requiredSkills: '',
    startDate: '',
    endDate: ''
  });

  // States pour les skills
  const [newSkill, setNewSkill] = useState({
    name: '',
    description: '',
    technoUtilisees: '',
    duree: ''
  });

  const [editingSkill, setEditingSkill] = useState(null);
  const [isSkillModalOpen, setIsSkillModalOpen] = useState(false);

  // ===== PROJECTS FUNCTIONS =====
  const fetchProjects = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/allprojects", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      if (!response.ok) throw new Error("Failed to fetch projects");
      const data = await response.json();
      setProjects(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const createProjectCard = async () => {
    try {
      const response = await fetch("http://127.0.0.1:8000/api/create/new/project", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(project),
      });

      if (response.ok) {
        setMessage("✅ Project created successfully!");
        setTimeout(() => setMessage(''), 3000);
      } else {
        const result = await response.json();
        setMessage(`❌ ${result.message}`);
      }

      fetchProjects();
      
      setProject({
        name: '',
        description: '',
        requiredSkills: '',
        startDate: '',
        endDate: ''
      });
    } catch (err) {
      console.error("Error creating project:", err);
      setMessage("❌ Error creating project");
    }
  };

  const deleteProjectCard = async (projectId) => {
    if (!window.confirm("Are you sure you want to delete this project?")) return;
    
    try {
      const response = await fetch(`http://127.0.0.1:8000/api/delete/project/${projectId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
      });

      if (response.ok) {
        setMessage("✅ Project deleted successfully!");
        setTimeout(() => setMessage(''), 3000);
        fetchProjects();
      } else {
        setMessage("❌ Failed to delete project");
      }
    } catch (err) {
      console.error("Error deleting project:", err);
      setMessage("❌ Error deleting project");
    }
  };

  // ===== SKILLS FUNCTIONS =====
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

  const createSkill = async () => {
    // Validation
    if (!newSkill.name || !newSkill.description || !newSkill.technoUtilisees || !newSkill.duree) {
      setMessage("❌ All fields are required for skill creation");
      return;
    }

    try {
      const response = await fetch("http://127.0.0.1:8000/api/skills/create", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(newSkill),
      });

      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.message}`);
        setTimeout(() => setMessage(''), 3000);
        
        // Réinitialiser le formulaire
        setNewSkill({
          name: '',
          description: '',
          technoUtilisees: '',
          duree: ''
        });
        
        // Recharger les compétences
        await fetchAvailableSkills();
        setIsSkillModalOpen(false);
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (err) {
      console.error("Error creating skill:", err);
      setMessage("❌ Error creating skill");
    }
  };

  const updateSkill = async (skillId) => {
    if (!editingSkill) return;

    try {
      const response = await fetch(`http://127.0.0.1:8000/api/skills/update/${skillId}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(editingSkill),
      });

      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.message}`);
        setTimeout(() => setMessage(''), 3000);
        
        // Recharger les compétences
        await fetchAvailableSkills();
        setEditingSkill(null);
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (err) {
      console.error("Error updating skill:", err);
      setMessage("❌ Error updating skill");
    }
  };

  const deleteSkill = async (skillId, skillName) => {
    if (!window.confirm(`Are you sure you want to delete the skill "${skillName}"?`)) return;

    try {
      const response = await fetch(`http://127.0.0.1:8000/api/skills/delete/${skillId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
      });

      const result = await response.json();

      if (result.success) {
        setMessage(`✅ ${result.message}`);
        setTimeout(() => setMessage(''), 3000);
        await fetchAvailableSkills();
      } else {
        setMessage(`❌ ${result.message}`);
      }
    } catch (err) {
      console.error("Error deleting skill:", err);
      setMessage("❌ Error deleting skill");
    }
  };

  const openEditSkillModal = (skill) => {
    setEditingSkill({
      id: skill.id,
      name: skill.name,
      description: skill.description,
      technoUtilisees: skill.technoUtilisees,
      duree: skill.duree
    });
  };

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
    fetchProjects();
    fetchAvailableSkills();
    fetchUserProjects();
  }, []);

  const filteredProjects = projects.filter(
    (proj) =>
      proj.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (proj.requiredSkills && proj.requiredSkills.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  if (loading) return <p className={styles.projectLoading}>Loading projects...</p>;
  if (error) return <p className={styles.projectError}>Error: {error}</p>;

  return (
    <div className={styles.projectPage}>
      {/* Notifications */}
      {message && (
        <div className={message.startsWith('✅') ? styles.projectNotificationSuccess : styles.projectNotificationError}>
          {message}
        </div>
      )}

      {/* Search bar */}
      <div className={styles.projectSearchContainer}>
        <input
          type="text"
          placeholder="🔍 Search project or skill..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className={styles.projectSearchInput}
        />
      </div>

      {/* User Projects Section */}
      <div className={styles.projectDashboardSection}>
        <div className={styles.projectUserProjectsSection}>
          <h2 className={styles.projectSectionTitle}>📁 My Projects</h2>

          {userProjects.length === 0 ? (
            <p className={styles.projectEmptyMessage}>You don't have any projects yet.</p>
          ) : (
            <div className={styles.projectGridContainer}>
              {userProjects.map((project) => (
                <div key={project.id} className={styles.projectCard}>
                  <div className={styles.projectCardHeader}>
                    <h3 className={styles.projectCardTitle}>{project.name}</h3>
                  </div>
                  <div className={styles.projectCardContent}>
                    <p className={styles.projectCardDescription}>{project.description}</p>
                    {project.requiredSkills && (
                      <p className={styles.projectCardSkills}>
                        <strong>Required Skills:</strong> {project.requiredSkills}
                      </p>
                    )}
                    <div className={styles.projectCardDates}>
                      <span>📅 Start: {new Date(project.startDate).toLocaleDateString()}</span>
                      <span>🏁 End: {new Date(project.endDate).toLocaleDateString()}</span>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Create Project Form */}
      <div className={styles.projectCreationContainer}>
        <h2 className={styles.projectCreationTitle}>Create New Project</h2>
        <div className={styles.projectFormGrid}>
          <input
            type="text"
            placeholder="Project Name"
            value={project.name}
            onChange={(e) => setProject({ ...project, name: e.target.value })}
            className={styles.projectInput}
          />
          
          <textarea
            placeholder="Description"
            value={project.description}
            onChange={(e) => setProject({ ...project, description: e.target.value })}
            className={styles.projectTextarea}
            rows="3"
          />
          
          <input
            type="text"
            placeholder="Required Skills (e.g., React, Node.js)"
            value={project.requiredSkills}
            onChange={(e) => setProject({ ...project, requiredSkills: e.target.value })}
            className={styles.projectInput}
          />
          
          <div className={styles.projectDateGroup}>
            <label className={styles.projectLabel}>Start Date</label>
            <input
              type="date"
              value={project.startDate}
              onChange={(e) => setProject({ ...project, startDate: e.target.value })}
              className={styles.projectInput}
            />
          </div>
          
          <div className={styles.projectDateGroup}>
            <label className={styles.projectLabel}>End Date</label>
            <input
              type="date"
              value={project.endDate}
              onChange={(e) => setProject({ ...project, endDate: e.target.value })}
              className={styles.projectInput}
            />
          </div>
        </div>
        
        <button onClick={createProjectCard} className={styles.projectCreateBtn}>
          ✨ Create Project
        </button>
      </div>

      {/* Skills Management Section */}
      <div className={styles.projectListContainer}>
        <div className={styles.skillsHeaderContainer}>
          <h2 className={styles.projectListTitle}>
            🛠️ All Skills ({availableSkills.length})
          </h2>
          <button 
            onClick={() => setIsSkillModalOpen(true)}
            className={styles.projectCreateBtn}
          >
            ➕ Add New Skill
          </button>
        </div>
        
        {availableSkills.length === 0 ? (
          <p className={styles.projectEmpty}>No skills available.</p>
        ) : (
          <div className={styles.projectGridContainer}>
            {availableSkills.map((skill) => (
              <div key={skill.id} className={styles.projectCard}>
                <div className={styles.projectCardHeader}>
                  <h3 className={styles.projectCardTitle}>{skill.name}</h3>
                  <div className={styles.skillActions}>
                    <button 
                      onClick={() => openEditSkillModal(skill)}
                      className={styles.projectEditBtn}
                      title="Edit"
                    >
                      ✏️
                    </button>
                    <button 
                      onClick={() => deleteSkill(skill.id, skill.name)}
                      className={styles.projectDeleteBtn}
                      title="Delete"
                    >
                      🗑️
                    </button>
                  </div>
                </div>
                <div className={styles.projectCardContent}>
                  <p className={styles.projectCardDescription}>{skill.description}</p>
                  <p className={styles.projectCardSkills}>
                    <strong>Technologies:</strong> {skill.technoUtilisees}
                  </p>
                  <p className={styles.projectCardSubtitle}>
                    <strong>Duration:</strong> {skill.duree || 'N/A'}
                  </p>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* All Projects List */}
      <div className={styles.projectListContainer}>
        <h2 className={styles.projectListTitle}>
          All Projects ({filteredProjects.length})
        </h2>
        
        {filteredProjects.length === 0 ? (
          <p className={styles.projectEmpty}>No projects found.</p>
        ) : (
          <div className={styles.projectGrid}>
            {filteredProjects.map((proj) => (
              <div key={proj.id} className={styles.projectCard}>
                <div className={styles.projectCardHeader}>
                  <h3 className={styles.projectCardTitle}>{proj.name}</h3>
                  <button 
                    onClick={() => deleteProjectCard(proj.id)}
                    className={styles.projectDeleteBtn}
                  >
                    🗑️
                  </button>
                </div>
                
                <p className={styles.projectCardDescription}>{proj.description}</p>
                
                <div className={styles.projectCardSkills}>
                  <strong>Skills:</strong> {proj.requiredSkills}
                </div>
                
                <div className={styles.projectCardDates}>
                  <div className={styles.projectDateItem}>
                    <span className={styles.projectDateLabel}>Start:</span>
                    <span className={styles.projectDateValue}>
                      {new Date(proj.startDate).toLocaleDateString()}
                    </span>
                  </div>
                  <div className={styles.projectDateItem}>
                    <span className={styles.projectDateLabel}>End:</span>
                    <span className={styles.projectDateValue}>
                      {new Date(proj.endDate).toLocaleDateString()}
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Modal for Creating New Skill */}
      {isSkillModalOpen && (
        <div className={styles.modalOverlay} onClick={() => setIsSkillModalOpen(false)}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.closeButton} onClick={() => setIsSkillModalOpen(false)}>×</button>
            <h2 className={styles.modalTitle}>Create New Skill</h2>
            
            <div className={styles.projectFormGrid}>
              <input
                type="text"
                placeholder="Skill Name"
                value={newSkill.name}
                onChange={(e) => setNewSkill({ ...newSkill, name: e.target.value })}
                className={styles.projectInput}
              />
              
              <textarea
                placeholder="Description (max 50 chars)"
                value={newSkill.description}
                onChange={(e) => setNewSkill({ ...newSkill, description: e.target.value })}
                className={styles.projectTextarea}
                maxLength={50}
                rows="2"
              />
              
              <input
                type="text"
                placeholder="Technologies Used"
                value={newSkill.technoUtilisees}
                onChange={(e) => setNewSkill({ ...newSkill, technoUtilisees: e.target.value })}
                className={styles.projectInput}
              />
              
              <div className={styles.projectDateGroup}>
                <label className={styles.projectLabel}>Duration Date</label>
                <input
                  type="date"
                  value={newSkill.duree}
                  onChange={(e) => setNewSkill({ ...newSkill, duree: e.target.value })}
                  className={styles.projectInput}
                />
              </div>
            </div>
            
            <button onClick={createSkill} className={styles.projectCreateBtn}>
              ✨ Create Skill
            </button>
          </div>
        </div>
      )}

      {/* Modal for Editing Skill */}
      {editingSkill && (
        <div className={styles.modalOverlay} onClick={() => setEditingSkill(null)}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.closeButton} onClick={() => setEditingSkill(null)}>×</button>
            <h2 className={styles.modalTitle}>Edit Skill</h2>
            
            <div className={styles.projectFormGrid}>
              <input
                type="text"
                placeholder="Skill Name"
                value={editingSkill.name}
                onChange={(e) => setEditingSkill({ ...editingSkill, name: e.target.value })}
                className={styles.projectInput}
              />
              
              <textarea
                placeholder="Description (max 50 chars)"
                value={editingSkill.description}
                onChange={(e) => setEditingSkill({ ...editingSkill, description: e.target.value })}
                className={styles.projectTextarea}
                maxLength={50}
                rows="2"
              />
              
              <input
                type="text"
                placeholder="Technologies Used"
                value={editingSkill.technoUtilisees}
                onChange={(e) => setEditingSkill({ ...editingSkill, technoUtilisees: e.target.value })}
                className={styles.projectInput}
              />
              
              <div className={styles.projectDateGroup}>
                <label className={styles.projectLabel}>Duration Date</label>
                <input
                  type="date"
                  value={editingSkill.duree}
                  onChange={(e) => setEditingSkill({ ...editingSkill, duree: e.target.value })}
                  className={styles.projectInput}
                />
              </div>
            </div>
            
            <button onClick={() => updateSkill(editingSkill.id)} className={styles.projectCreateBtn}>
              💾 Update Skill
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default Projects;