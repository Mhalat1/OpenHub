import React, { useEffect, useState } from 'react';
import styles from '../style/Projects.module.css';

const Projects = () => {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [message, setMessage] = useState('');
  
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

  useEffect(() => {
    fetchProjects();
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