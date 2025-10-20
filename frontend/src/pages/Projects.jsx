import React, { useEffect, useState } from 'react';
import styles from '../style/Projects.module.css';

const Projects = () => {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
    const [message, setMessage] = useState('');
  
  // ✅ Initialiser project comme un objet
  const [project, setProject] = useState({
    name: '',
    description: '',
    requiredSkills: '',
    startDate: '',
    endDate: ''
  });

  // Fetch projects from API
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
      } else {
        setMessage(`❌ ${result.message}`);
      }

      // ✅ Recharger la liste des projets après création
      fetchProjects();
      
      // ✅ Réinitialiser le formulaire
      setProject({
        name: '',
        description: '',
        requiredSkills: '',
        startDate: '',
        endDate: ''
      });
      
      return data;
    } catch (err) {
      console.error("Error creating project:", err);
    }
  };

  useEffect(() => {
    fetchProjects();
    // ✅ Ne PAS appeler createProjectCard() ici
  }, []);

  // Filter projects by name or required skills
  const filteredProjects = projects.filter(
    (proj) =>
      proj.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      (proj.requiredSkills && proj.requiredSkills.toLowerCase().includes(searchTerm.toLowerCase()))
  );

  if (loading) return <p>Loading projects...</p>;
  if (error) return <p>Error: {error}</p>;

  return (
    <div>
      {/* Search bar */}
      <input
        type="text"
        placeholder="Search project or skill..."
        value={searchTerm}
        onChange={(e) => setSearchTerm(e.target.value)}
        className={styles.searchInput}
      />

        <h2>Project creation</h2>
      <div className={styles.projectsContainer}>
        
        {/* ✅ Syntaxe correcte pour les inputs */}
        <input
          type="text"
          placeholder="Project Name"
          value={project.name}
          onChange={(e) => setProject({ ...project, name: e.target.value })}
        />
        
        <input
          type="text"
          placeholder="Description"
          value={project.description}
          onChange={(e) => setProject({ ...project, description: e.target.value })}
        />
        
        <input
          type="text"
          placeholder="Required Skills"
          value={project.requiredSkills}
          onChange={(e) => setProject({ ...project, requiredSkills: e.target.value })}
        />
        
        <input
          type="date"
          placeholder="Start Date"
          value={project.startDate}
          onChange={(e) => setProject({ ...project, startDate: e.target.value })}
        />
        
        <input
          type="date"
          placeholder="End Date"
          value={project.endDate}
          onChange={(e) => setProject({ ...project, endDate: e.target.value })}
        />
        
        <button onClick={createProjectCard}>Create Project</button>
      </div>

      {/* Projects list */}
      <div className={styles.projectsContainer}>
        {filteredProjects.length === 0 ? (
          <p>No projects found.</p>
        ) : (
          filteredProjects.map((proj) => (
            <div key={proj.id} className={styles.projectCard}>
              <h2>{proj.name}</h2>
              <p>{proj.description}</p>
              <p><strong>Skills:</strong> {proj.requiredSkills}</p>
              <p><strong>Start Date:</strong> {new Date(proj.startDate).toLocaleDateString()}</p>
              <p><strong>End Date:</strong> {new Date(proj.endDate).toLocaleDateString()}</p>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default Projects;