import React, { useEffect, useState } from 'react';
import styles from '../style/Projects.module.css';

const Projects = () => {
  const [projects, setProjects] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');

  // Fetch projects from API
  const fetchProjects = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/projet", {
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

  useEffect(() => {
    fetchProjects();
  }, []);

  // Filter projects by name or skills
  const filteredProjects = projects.filter(
    (project) =>
      project.nom.toLowerCase().includes(searchTerm.toLowerCase()) ||
      project.competencesNecessaires.toLowerCase().includes(searchTerm.toLowerCase())
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

      <div className={styles.projectsContainer}>
        {filteredProjects.length === 0 ? (
          <p>No projects found.</p>
        ) : (
          filteredProjects.map((project) => (
            <div key={project.id} className={styles.projectCard}>
              <h2>{project.nom}</h2>
              <p>{project.description}</p>
              <p><strong>Skills:</strong> {project.competencesNecessaires}</p>
              <p><strong>Start Date:</strong> {new Date(project.dateDeCreation).toLocaleDateString()}</p>
              <p><strong>End Date:</strong> {new Date(project.dateDeFin).toLocaleDateString()}</p>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default Projects;
