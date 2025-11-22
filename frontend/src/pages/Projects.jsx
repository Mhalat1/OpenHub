import React, { useEffect, useState } from 'react';
import styles from '../style/Projects.module.css';

const API_URL = import.meta.env.VITE_API_URL;

const Projects = () => {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [message, setMessage] = useState({ text: '', type: '' });
  const [actionLoading, setActionLoading] = useState({ type: '', id: null });

  // States pour les projets
  const [project, setProject] = useState({
    name: '',
    description: '',
    requiredSkills: '',
    startDate: '',
    endDate: ''
  });

  // État pour l'édition de projet
  const [editingProject, setEditingProject] = useState(null);

  // States pour les skills
  const [newSkill, setNewSkill] = useState({
    name: '',
    description: '',
    technoUtilisees: '',
    duree: ''
  });

  const [editingSkill, setEditingSkill] = useState(null);
  const [isSkillModalOpen, setIsSkillModalOpen] = useState(false);
  const [isProjectModalOpen, setIsProjectModalOpen] = useState(false);
  const [availableSkills, setAvailableSkills] = useState([]);

  // ===== NOTIFICATION HELPER =====
  const showMessage = (text, type = 'info') => {
    setMessage({ text, type });
    setTimeout(() => setMessage({ text: '', type: '' }), 4000);
  };

  // ===== PROJECTS FUNCTIONS =====
  const fetchProjects = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch(`${API_URL}/api/allprojects`, {
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
      showMessage(`❌ ${err.message}`, 'error');
    } finally {
      setLoading(false);
    }
  };

  const createProject = async () => {
    if (!project.name || !project.description) {
      showMessage('❌ Project name and description are required', 'error');
      return;
    }

    setActionLoading({ type: 'createProject', id: null });
    
    try {
      const response = await fetch(`${API_URL}/api/create/new/project`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(project),
      });

      const result = await response.json();

      if (response.ok) {
        showMessage("✅ Project created successfully!", 'success');
        await fetchProjects();
        setIsProjectModalOpen(false);
        setProject({
          name: '',
          description: '',
          requiredSkills: '',
          startDate: '',
          endDate: ''
        });
      } else {
        showMessage(`❌ ${result.message}`, 'error');
      }
    } catch (err) {
      console.error("Error creating project:", err);
      showMessage("❌ Error creating project", 'error');
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  const updateProject = async (projectId) => {
    if (!editingProject) return;

    setActionLoading({ type: 'updateProject', id: projectId });

    try {
      const response = await fetch(`${API_URL}/api/modify/project/${projectId}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(editingProject),
      });

      const result = await response.json();

      if (response.ok) {
        showMessage(`✅ ${result.message}`, 'success');
        await fetchProjects();
        setEditingProject(null);
      } else {
        showMessage(`❌ ${result.message}`, 'error');
      }
    } catch (err) {
      console.error("Error updating project:", err);
      showMessage("❌ Error updating project", 'error');
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  const openEditProjectModal = (proj) => {
    setEditingProject({
      id: proj.id,
      name: proj.name,
      description: proj.description,
      requiredSkills: proj.requiredSkills,
      startDate: proj.startDate.split('T')[0],
      endDate: proj.endDate.split('T')[0]
    });
  };

  const deleteProject = async (projectId, projectName) => {
    if (!window.confirm(`Are you sure you want to delete "${projectName}"?`)) return;
    
    setActionLoading({ type: 'deleteProject', id: projectId });

    try {
      const response = await fetch(`${API_URL}/api/delete/project/${projectId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
      });

      if (response.ok) {
        showMessage("✅ Project deleted successfully!", 'success');
        await fetchProjects();
      } else {
        showMessage("❌ Failed to delete project", 'error');
      }
    } catch (err) {
      console.error("Error deleting project:", err);
      showMessage("❌ Error deleting project", 'error');
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  // ===== SKILLS FUNCTIONS =====
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

      if (!response.ok) throw new Error(`Skills API error: ${response.status}`);
      
      const data = await response.json();
      setAvailableSkills(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error("Error fetching skills:", error);
      showMessage("❌ Error fetching skills", 'error');
    }
  };

  const createSkill = async () => {
    if (!newSkill.name || !newSkill.description || !newSkill.technoUtilisees) {
      showMessage("❌ Skill name, description and technologies are required", 'error');
      return;
    }

    setActionLoading({ type: 'createSkill', id: null });

    try {
      const response = await fetch(`${API_URL}/api/skills/create`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(newSkill),
      });

      const result = await response.json();

      if (result.success) {
        showMessage(`✅ ${result.message}`, 'success');
        setNewSkill({
          name: '',
          description: '',
          technoUtilisees: '',
          duree: ''
        });
        await fetchAvailableSkills();
        setIsSkillModalOpen(false);
      } else {
        showMessage(`❌ ${result.message}`, 'error');
      }
    } catch (err) {
      console.error("Error creating skill:", err);
      showMessage("❌ Error creating skill", 'error');
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  const updateSkill = async (skillId) => {
    if (!editingSkill) return;

    setActionLoading({ type: 'updateSkill', id: skillId });

    try {
      const response = await fetch(`${API_URL}/api/skills/update/${skillId}`, {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
        body: JSON.stringify(editingSkill),
      });

      const result = await response.json();

      if (result.success) {
        showMessage(`✅ ${result.message}`, 'success');
        await fetchAvailableSkills();
        setEditingSkill(null);
      } else {
        showMessage(`❌ ${result.message}`, 'error');
      }
    } catch (err) {
      console.error("Error updating skill:", err);
      showMessage("❌ Error updating skill", 'error');
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  const deleteSkill = async (skillId, skillName) => {
    if (!window.confirm(`Are you sure you want to delete "${skillName}"?`)) return;

    setActionLoading({ type: 'deleteSkill', id: skillId });

    try {
      const response = await fetch(`${API_URL}/api/skills/delete/${skillId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${localStorage.getItem("token")}`,
        },
      });

      const result = await response.json();

      if (result.success) {
        showMessage(`✅ ${result.message}`, 'success');
        await fetchAvailableSkills();
      } else {
        showMessage(`❌ ${result.message}`, 'error');
      }
    } catch (err) {
      console.error("Error deleting skill:", err);
      showMessage("❌ Error deleting skill", 'error');
    } finally {
      setActionLoading({ type: '', id: null });
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

  useEffect(() => {
    const init = async () => {
      setLoading(true);
      await Promise.all([fetchProjects(), fetchAvailableSkills()]);
      setLoading(false);
    };
    init();
  }, []);

  const filteredProjects = projects.filter(
    (proj) =>
      proj.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (proj.requiredSkills && proj.requiredSkills.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  const filteredSkills = availableSkills.filter(
    (skill) =>
      skill.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      skill.technoUtilisees.toLowerCase().includes(searchTerm.toLowerCase())
  );

  if (loading) return (
    <div className={styles.loadingContainer}>
      <div className={styles.spinner}></div>
      <p>Loading projects and skills...</p>
    </div>
  );

  if (error) return (
    <div className={styles.errorContainer}>
      <p>Error: {error}</p>
    </div>
  );

  return (
    <div className={styles.projectsPage}>
      {/* Header */}
      <div className={styles.header}>
        <h1 className={styles.pageTitle}>Projects & Skills Management</h1>
        <div className={styles.searchContainer}>
          <input
            type="text"
            placeholder="🔍 Search projects or skills..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className={styles.searchInput}
          />
        </div>
      </div>

      {/* Notification */}
      {message.text && (
        <div className={`${styles.notification} ${styles[message.type]}`}>
          {message.text}
        </div>
      )}

      {/* Main Content */}
      <div className={styles.mainContent}>
        
        {/* Skills Section */}
        <section className={styles.section}>
          <div className={styles.sectionHeader}>
            <h2 className={styles.sectionTitle}>
              🛠️ Skills Management ({availableSkills.length})
            </h2>
            <button 
              onClick={() => setIsSkillModalOpen(true)}
              className={styles.primaryButton}
            >
              ✨ Create New Skill
            </button>
          </div>

          {availableSkills.length === 0 ? (
            <div className={styles.emptyState}>
              <p>No skills available</p>
              <p className={styles.emptyStateSubtitle}>Create your first skill to get started</p>
            </div>
          ) : (
            <div className={styles.cardsGrid}>
              {filteredSkills.map((skill) => (
                <div key={skill.id} className={styles.card}>
                  <div className={styles.cardHeader}>
                    <h3 className={styles.cardTitle}>{skill.name}</h3>
                    <div className={styles.cardActions}>
                      <button 
                        onClick={() => openEditSkillModal(skill)}
                        className={styles.editButton}
                        disabled={actionLoading.type === 'updateSkill' && actionLoading.id === skill.id}
                        title="Edit skill"
                      >
                        {actionLoading.type === 'updateSkill' && actionLoading.id === skill.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          '✏️'
                        )}
                      </button>
                      <button 
                        onClick={() => deleteSkill(skill.id, skill.name)}
                        className={styles.deleteButton}
                        disabled={actionLoading.type === 'deleteSkill' && actionLoading.id === skill.id}
                        title="Delete skill"
                      >
                        {actionLoading.type === 'deleteSkill' && actionLoading.id === skill.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          '🗑️'
                        )}
                      </button>
                    </div>
                  </div>
                  
                  <div className={styles.cardContent}>
                    <p className={styles.cardDescription}>{skill.description}</p>
                    <div className={styles.cardMeta}>
                      <span className={styles.metaItem}>
                        <strong>Technologies:</strong> {skill.technoUtilisees}
                      </span>
                      {skill.duree && (
                        <span className={styles.metaItem}>
                          <strong>Duration:</strong> {skill.duree}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>

        {/* Projects Section */}
        <section className={styles.section}>
          <div className={styles.sectionHeader}>
            <h2 className={styles.sectionTitle}>
              📁 Projects Management ({projects.length})
            </h2>
            <button 
              onClick={() => setIsProjectModalOpen(true)} 
              className={styles.primaryButton}
            >
              ✨ Create New Project
            </button>
          </div>

          {projects.length === 0 ? (
            <div className={styles.emptyState}>
              <p>No projects available</p>
              <p className={styles.emptyStateSubtitle}>Create your first project to get started</p>
            </div>
          ) : (
            <div className={styles.cardsGrid}>
              {filteredProjects.map((proj) => (
                <div key={proj.id} className={styles.card}>
                  <div className={styles.cardHeader}>
                    <h3 className={styles.cardTitle}>{proj.name}</h3>
                    <div className={styles.cardActions}>
                      <button 
                        onClick={() => openEditProjectModal(proj)}
                        className={styles.editButton}
                        disabled={actionLoading.type === 'updateProject' && actionLoading.id === proj.id}
                        title="Edit project"
                      >
                        {actionLoading.type === 'updateProject' && actionLoading.id === proj.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          '✏️'
                        )}
                      </button>
                      <button 
                        onClick={() => deleteProject(proj.id, proj.name)}
                        className={styles.deleteButton}
                        disabled={actionLoading.type === 'deleteProject' && actionLoading.id === proj.id}
                        title="Delete project"
                      >
                        {actionLoading.type === 'deleteProject' && actionLoading.id === proj.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          '🗑️'
                        )}
                      </button>
                    </div>
                  </div>
                  
                  <div className={styles.cardContent}>
                    <p className={styles.cardDescription}>{proj.description}</p>
                    
                    <div className={styles.cardMeta}>
                      <span className={styles.metaItem}>
                        <strong>Skills:</strong> {proj.requiredSkills}
                      </span>
                    </div>

                    <div className={styles.datesContainer}>
                      <div className={styles.dateItem}>
                        <span className={styles.dateLabel}>Start:</span>
                        <span className={styles.dateValue}>
                          {new Date(proj.startDate).toLocaleDateString()}
                        </span>
                      </div>
                      <div className={styles.dateItem}>
                        <span className={styles.dateLabel}>End:</span>
                        <span className={styles.dateValue}>
                          {new Date(proj.endDate).toLocaleDateString()}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>

      {/* Create Project Modal */}
      {isProjectModalOpen && (
        <div className={styles.modalOverlay} onClick={() => setIsProjectModalOpen(false)}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.closeButton} onClick={() => setIsProjectModalOpen(false)}>×</button>
            <h2 className={styles.modalTitle}>Create New Project</h2>

            <div className={styles.formGrid}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Project Name *</label>
                <input
                  type="text"
                  placeholder="Enter project name"
                  value={project.name}
                  onChange={(e) => setProject({ ...project, name: e.target.value })}
                  className={styles.formInput}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Description *</label>
                <textarea
                  placeholder="Describe the project..."
                  value={project.description}
                  onChange={(e) => setProject({ ...project, description: e.target.value })}
                  className={styles.formTextarea}
                  rows="3"
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Required Skills</label>
                <input
                  type="text"
                  placeholder="React, Node.js, MongoDB..."
                  value={project.requiredSkills}
                  onChange={(e) => setProject({ ...project, requiredSkills: e.target.value })}
                  className={styles.formInput}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Start Date</label>
                <input
                  type="date"
                  value={project.startDate}
                  onChange={(e) => setProject({ ...project, startDate: e.target.value })}
                  className={styles.formInput}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>End Date</label>
                <input
                  type="date"
                  value={project.endDate}
                  onChange={(e) => setProject({ ...project, endDate: e.target.value })}
                  className={styles.formInput}
                />
              </div>
            </div>

            <button 
              onClick={createProject}
              className={styles.primaryButton}
              disabled={actionLoading.type === 'createProject'}
            >
              {actionLoading.type === 'createProject' ? (
                <>
                  <span className={styles.spinner}></span>
                  Creating...
                </>
              ) : (
                '✨ Create Project'
              )}
            </button>
          </div>
        </div>
      )}

      {/* Create Skill Modal */}
      {isSkillModalOpen && (
        <div className={styles.modalOverlay} onClick={() => setIsSkillModalOpen(false)}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.closeButton} onClick={() => setIsSkillModalOpen(false)}>×</button>
            <h2 className={styles.modalTitle}>Create New Skill</h2>
            
            <div className={styles.formGrid}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Skill Name *</label>
                <input
                  type="text"
                  placeholder="Enter skill name"
                  value={newSkill.name}
                  onChange={(e) => setNewSkill({ ...newSkill, name: e.target.value })}
                  className={styles.formInput}
                />
              </div>
              
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Description *</label>
                <textarea
                  placeholder="Describe the skill..."
                  value={newSkill.description}
                  onChange={(e) => setNewSkill({ ...newSkill, description: e.target.value })}
                  className={styles.formTextarea}
                  maxLength={50}
                  rows="2"
                />
                <span className={styles.charCount}>{newSkill.description.length}/50</span>
              </div>
              
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Technologies Used *</label>
                <input
                  type="text"
                  placeholder="React, JavaScript, Node.js..."
                  value={newSkill.technoUtilisees}
                  onChange={(e) => setNewSkill({ ...newSkill, technoUtilisees: e.target.value })}
                  className={styles.formInput}
                />
              </div>
              
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Duration</label>
                <input
                  type="text"
                  placeholder="3 months, 1 year..."
                  value={newSkill.duree}
                  onChange={(e) => setNewSkill({ ...newSkill, duree: e.target.value })}
                  className={styles.formInput}
                />
              </div>
            </div>
            
            <button 
              onClick={createSkill}
              className={styles.primaryButton}
              disabled={actionLoading.type === 'createSkill'}
            >
              {actionLoading.type === 'createSkill' ? (
                <>
                  <span className={styles.spinner}></span>
                  Creating...
                </>
              ) : (
                '✨ Create Skill'
              )}
            </button>
          </div>
        </div>
      )}

      {/* Edit Skill Modal */}
      {editingSkill && (
        <div className={styles.modalOverlay} onClick={() => setEditingSkill(null)}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.closeButton} onClick={() => setEditingSkill(null)}>×</button>
            <h2 className={styles.modalTitle}>Edit Skill</h2>
            
            <div className={styles.formGrid}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Skill Name *</label>
                <input
                  type="text"
                  placeholder="Enter skill name"
                  value={editingSkill.name}
                  onChange={(e) => setEditingSkill({ ...editingSkill, name: e.target.value })}
                  className={styles.formInput}
                />
              </div>
              
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Description *</label>
                <textarea
                  placeholder="Describe the skill..."
                  value={editingSkill.description}
                  onChange={(e) => setEditingSkill({ ...editingSkill, description: e.target.value })}
                  className={styles.formTextarea}
                  maxLength={50}
                  rows="2"
                />
                <span className={styles.charCount}>{editingSkill.description.length}/50</span>
              </div>
              
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Technologies Used *</label>
                <input
                  type="text"
                  placeholder="React, JavaScript, Node.js..."
                  value={editingSkill.technoUtilisees}
                  onChange={(e) => setEditingSkill({ ...editingSkill, technoUtilisees: e.target.value })}
                  className={styles.formInput}
                />
              </div>
              
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Date de fin</label>
                <input
                  type="text"
                  placeholder="../../...."
                  value={editingSkill.duree}
                  onChange={(e) => setEditingSkill({ ...editingSkill, duree: e.target.value })}
                  className={styles.formInput}
                />
              </div>
            </div>
            
            <button 
              onClick={() => updateSkill(editingSkill.id)}
              className={styles.primaryButton}
              disabled={actionLoading.type === 'updateSkill' && actionLoading.id === editingSkill.id}
            >
              {actionLoading.type === 'updateSkill' && actionLoading.id === editingSkill.id ? (
                <>
                  <span className={styles.spinner}></span>
                  Updating...
                </>
              ) : (
                '💾 Update Skill'
              )}
            </button>
          </div>
        </div>
      )}

      {/* Edit Project Modal */}
      {editingProject && (
        <div className={styles.modalOverlay} onClick={() => setEditingProject(null)}>
          <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
            <button className={styles.closeButton} onClick={() => setEditingProject(null)}>×</button>
            <h2 className={styles.modalTitle}>Edit Project</h2>
            
            <div className={styles.formGrid}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Project Name *</label>
                <input
                  type="text"
                  placeholder="Enter project name"
                  value={editingProject.name}
                  onChange={(e) => setEditingProject({ ...editingProject, name: e.target.value })}
                  className={styles.formInput}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Description *</label>
                <textarea
                  placeholder="Describe the project..."
                  value={editingProject.description}
                  onChange={(e) => setEditingProject({ ...editingProject, description: e.target.value })}
                  className={styles.formTextarea}
                  rows="3"
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Required Skills</label>
                <input
                  type="text"
                  placeholder="React, Node.js, MongoDB..."
                  value={editingProject.requiredSkills}
                  onChange={(e) => setEditingProject({ ...editingProject, requiredSkills: e.target.value })}
                  className={styles.formInput}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Start Date</label>
                <input
                  type="date"
                  value={editingProject.startDate}
                  onChange={(e) => setEditingProject({ ...editingProject, startDate: e.target.value })}
                  className={styles.formInput}
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>End Date</label>
                <input
                  type="date"
                  value={editingProject.endDate}
                  onChange={(e) => setEditingProject({ ...editingProject, endDate: e.target.value })}
                  className={styles.formInput}
                />
              </div>
            </div>
            
            <button 
              onClick={() => updateProject(editingProject.id)}
              className={styles.primaryButton}
              disabled={actionLoading.type === 'updateProject' && actionLoading.id === editingProject.id}
            >
              {actionLoading.type === 'updateProject' && actionLoading.id === editingProject.id ? (
                <>
                  <span className={styles.spinner}></span>
                  Updating...
                </>
              ) : (
                '💾 Update Project'
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

export default Projects;