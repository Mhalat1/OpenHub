import React, { useState, useEffect } from 'react';
import styles from '../style/Message.module.css';

const Messages = () => {
  const [userData, setUserData] = useState(null);
  const [friends, setFriends] = useState([]);
  const [conversations, setConversations] = useState([]); 
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [message, setMessage] = useState([]); 

  const fetchData = async () => {
    const token = localStorage.getItem("token");

    if (!token) {
      setError("No authentication token found");
      setLoading(false);
      return;
    }

    try {
      const userResponse = await fetch("http://127.0.0.1:8000/api/getConnectedUser", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      const data = await userResponse.json();
      if (!userResponse.ok) {
        throw new Error(data.message || `User API error: ${userResponse.status}`);
      }
      setUserData(data);
    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchUserFriends = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/user/friends", {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });
      const data = await response.json();
      setFriends(data);
    } catch (error) {
      setError(error.message);
    }
  };


const fetchConversations = async () => {
  const token = localStorage.getItem("token");
  try {
    const response = await fetch("http://127.0.0.1:8000/api/get/conversations", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${token}`,
      },
    });

    const data = await response.json();
    console.log("Conversations:", data);

    setConversations(data);
  } catch (error) {
    setError(error.message);
  }
};


const fetchMessages = async () => {
  const token = localStorage.getItem("token");
  try {
    const response = await fetch("http://127.0.0.1:8000/api/get/messages", {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${token}`,
      },
    });
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.message || `Messages API error: ${response.status}`);
    }
    // ✅ Toujours mettre à jour, même si vide
    setMessage(data || []);
  } catch (error) {
    setError(error.message);
  }
};



  const createConversation = async (title, description) => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch("http://127.0.0.1:8000/api/create/conversation", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({ title, description }),
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message || `Create Conversation API error: ${response.status}`);
      }
      
      return data;
    } catch (error) {
      setError(error.message);
    }
  };

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [success, setSuccess] = useState(null);

  const handleCreateConversation = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    try {
      const data = await createConversation(title, description);
      if (data) {
        setSuccess("Conversation created successfully!");
        setTitle("");
        setDescription("");
        
        fetchConversations(); // Refresh conversations list
      }
    } catch (error) {
      setError(error.message);
    }
  };











const [content, setContent] = useState("");
const [conversation_id, setConversation] = useState("");
const [messageLoading, setMessageLoading] = useState(false); // État séparé pour éviter conflit

// Fonction pour créer un message
const createMessage = async (content, conversation_id) => {
  const token = localStorage.getItem("token");
  try {
    const response = await fetch("http://127.0.0.1:8000/api/create/message", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "Authorization": `Bearer ${token}`,
      },
      body: JSON.stringify({ content, conversation_id }),
    });
    const data = await response.json();
    if (!response.ok) {
      throw new Error(data.message || `Create Message API error: ${response.status}`);
    }
    return data;
  } catch (error) {
    throw error;
  }
};


// Handler pour créer un message
const handleCreateMessage = async (e) => {
  e.preventDefault();
  setError(null);
  setSuccess(null);
  setMessageLoading(true);

  try {
    const data = await createMessage(content, conversation_id);
    if (data) {
      setSuccess("Message created successfully!");
      setContent("");
      setConversation("");
      
      // ✅ Rafraîchir les messages ET les conversations
      await fetchMessages();
      await fetchConversations(); // Pour mettre à jour lastMessageAt
    }
  } catch (error) {
    if (error.message.includes('404') || error.message.includes('not found')) {
      setError(`Conversation ID "${conversation_id}" does not exist.`);
    } else if (error.message.includes('401')) {
      setError('You must be logged in to create a message.');
    } else {
      setError(error.message);
    }
  } finally {
    setMessageLoading(false);
  }
};

  useEffect(() => {
    fetchData();
    fetchUserFriends();
    fetchConversations();
    fetchMessages();
  }, []);

  if (loading) return <p className={styles["msg-loading"]}>Loading...</p>;
  if (error) return <p className={styles["msg-error"]}>{error}</p>;

  return (
    <div className={styles["msg-container"]}>
      {/* === User Info === */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Connected User Information</h2>
        {userData ? (
          <div className={styles["msg-user-card"]}>
            <p><strong>ID:</strong> {userData.id}</p>
            <p><strong>Email:</strong> {userData.email}</p>
            <p><strong>First Name:</strong> {userData.firstName}</p>
            <p><strong>Last Name:</strong> {userData.lastName}</p>
            <p><strong>Availability Start:</strong> {userData.availabilityStart || 'N/A'}</p>
            <p><strong>Availability End:</strong> {userData.availabilityEnd || 'N/A'}</p>
          </div>
        ) : (
          <p className={styles["msg-empty-message"]}>No user data available.</p>
        )}
      </section>

      {/* === Friends List === */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>User Friends</h2>
        {friends.length > 0 ? (
          <ul className={styles["msg-friend-list"]}>
            {friends.map((friend) => (
              <li key={friend.id} className={styles["msg-friend-card"]}>
                <p><strong>ID:</strong> {friend.id}</p>
                <p><strong>Email:</strong> {friend.email}</p>
                <p><strong>First Name:</strong> {friend.firstName}</p>
                <p><strong>Last Name:</strong> {friend.lastName}</p>
              </li>
            ))}
          </ul>
        ) : (
          <p className={styles["msg-empty-message"]}>No friends data available.</p>
        )}
      </section>

      <div className={styles["msg-container"]}>
      {/* Formulaire création */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Créer une conversation</h2>
        <form onSubmit={handleCreateConversation}>
          <div>
            <label>Titre :</label>
            <input
              type="text"
              value={title}
              onChange={(e) => setTitle(e.target.value)}
              required
            />
          </div>
          <div>
            <label>Description :</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={loading}>
            {loading ? "Création..." : "Créer"}
          </button>
        </form>
        {success && <p style={{ color: "green" }}>{success}</p>}
        {error && <p style={{ color: "red" }}>{error}</p>}
      </section>








      {/* Formulaire création */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Create message</h2>
        <form onSubmit={handleCreateMessage}>

          <div>
            <label>Content :</label>
            <textarea
              value={content}
              onChange={(e) => setContent(e.target.value)}
              required
            />
          </div>
{/* Replace the conversation_id textarea with a select dropdown */}
<div>
  <label>Conversation :</label>
  <select
    value={conversation_id}
    onChange={(e) => setConversation(e.target.value)}
    required
  >
    <option value="">-- Select a conversation --</option>
    {conversations.map((conv) => (
      <option key={conv.id} value={conv.id}>
        {conv.title || `Conversation #${conv.id}`}
      </option>
    ))}
  </select>
</div>
          <button type="submit" disabled={loading}>
            {loading ? "Création..." : "Créer"}
          </button>
        </form>
        {success && <p style={{ color: "green" }}>{success}</p>}
        {error && <p style={{ color: "red" }}>{error}</p>}
      </section>




      {/* Liste des conversations */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Conversations</h2>
        {conversations.length > 0 ? (
          <ul className={styles["msg-conversationsList"]}>
            {conversations.map((conv) => (
              <li key={conv.id} className={styles["msg-conversationItem"]}>
                <p><strong>ID :</strong> {conv.id}</p>
                <p><strong>Titre :</strong> {conv.title}</p>
                <p><strong>Description :</strong> {conv.description}</p>
                <p><strong>Créée par :</strong> {conv.createdBy}</p>
                <p><strong>Créée le :</strong> {conv.createdAt}</p>
                <p><strong>Dernier message :</strong> {conv.lastMessageAt || "Aucun"}</p>
              </li>
            ))}
          </ul>
        ) : (
          <p>Aucune conversation disponible.</p>
        )}
      </section>
    </div>


      {/* === Messages Info === */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Messages</h2>
        {message.length > 0 ? (
          <ul className={styles["msg-messagesList"]}>
            {message.map((msg) => (
              <li key={msg.id} className={styles["msg-messageItem"]}>
                <p><strong>conversationTitle:</strong> {msg.conversationTitle}</p>
                <p><strong>content:</strong> {msg.content}</p>
                <p><strong>author:</strong> {msg.author}</p>
                <p><strong>createdAt:</strong> {msg.createdAt}</p>
                <p><strong>conversationId:</strong> {msg.conversationId}</p>
                <p><strong>authorName:</strong> {msg.authorName}</p>

              </li>
            ))}
          </ul>
        ) : (
          <p className={styles["msg-empty-message"]}>No messages data available.</p>
        )}
      </section>  
    </div>
  );
};

export default Messages;
