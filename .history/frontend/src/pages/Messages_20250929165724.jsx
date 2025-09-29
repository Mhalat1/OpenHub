// const Messages = () => {
//       const token = localStorage.getItem("token");
//       console.log(token);
//       <h2>token</h2>
//   return (
//     <div>
//       <p>12</p>
//       <h2>Messages</h2>
//       <p>token : {token}</p>
//     </div>
//   );

// };

// export default Messages;


import React, { useEffect, useState } from 'react';
import styles from '../style/Projects.module.css';

const Messages = () => {
  const [messages, setMessages] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [token, setToken] = useState(''); // État pour stocker le token
  
  // Fetch messages from API
  const fetchMessages = async () => {
    const storedToken = localStorage.getItem("token");
    setToken(storedToken || 'No token'); // Stocker le token dans l'état
    
    if (!storedToken) {
      setError("No token found. Please login.");
      setLoading(false);
      return;
    }

    try {
      const response = await fetch("http://127.0.0.1:8000/api/getMessage", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${storedToken}`,
        },
      });

      if (!response.ok) throw new Error(`Failed to fetch messages: ${response.status}`);
      
      const data = await response.json();
      console.log("Messages received:", data);
      setMessages(data);
    } catch (err) {
      setError(err.message);
      console.error("Error fetching messages:", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchMessages();
  }, []);

  // Filter messages by title or content
  const filteredMessages = messages.filter(
    (message) =>
      message.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
      message.content?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  if (loading) return <p>Loading messages...</p>;
  if (error) return <p>Error: {error}</p>;

  return (
    <div className={styles.projectsContainer}>
      <h1>My Messages</h1>
      
      {/* Affichage du token */}
      <div style={{ 
        padding: '10px', 
        background: '#f0f0f0', 
        borderRadius: '5px', 
        marginBottom: '20px',
        wordBreak: 'break-all',
        fontSize: '0.85em'
      }}>
        <strong>Token:</strong> {token}
      </div>
      
      {/* Search bar */}
      <div className={styles.searchBar}>
        <input
          type="text"
          placeholder="Search messages..."
          value={searchTerm}
          onChange={(e) => setSearchTerm(e.target.value)}
          className={styles.searchInput}
        />
      </div>

      {/* Messages list */}
      {filteredMessages.length === 0 ? (
        <p>No messages found.</p>
      ) : (
        <div className={styles.projectsList}>
          {filteredMessages.map((message) => (
            <div key={message.id} className={styles.projectCard}>
              <h3>{message.title}</h3>
              <p><strong>From:</strong> User {message.sender}</p>
              <p><strong>To:</strong> User {message.recipient}</p>
              <p><strong>Sent:</strong> {new Date(message.sent_at).toLocaleString()}</p>
              <p>{message.content}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default Messages;