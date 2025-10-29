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
  const [project, setProject] = useState({
    name: '',
    description: '',
    requiredSkills: '',
    startDate: '',
    endDate: ''
  });

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


      <div className={styles.projectDashboardSection}>

  {/* 🔹 User Projects Section */}
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





      {/* Creation form */}
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





      {/* skills list */}
      <div className={styles.projectListContainer}>
        <h2 className={styles.projectListTitle}>
          All Skills ({availableSkills.length})
        </h2>
        
        {availableSkills.length === 0 ? (
          <p className={styles.projectEmpty}>No projects found.</p>
        ) : (
  <div className={styles.projectSkillsSection}>
    <h2 className={styles.projectSectionTitle}>🛠️ Available Skills</h2>

    {availableSkills.length === 0 ? (
      <p className={styles.projectEmptyMessage}>No skills available yet.</p>
    ) : (
      <div className={styles.projectGridContainer}>
        {availableSkills.map((skill) => (
          <div key={skill.id} className={styles.projectCard}>
            <div className={styles.projectCardContent}>
              <h3 className={styles.projectCardTitle}>{skill.name}</h3>
              <p className={styles.projectCardSubtitle}>
                {skill.category ? `Category: ${skill.category}` : "No category"}
              </p>
            </div>
          </div>
        ))}
      </div>
    )}
  </div>
        )}
      </div>


      {/* Projects list */}
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
    </div>
  );
};

export default Projects;