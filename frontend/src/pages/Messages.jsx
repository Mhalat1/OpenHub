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
      if  (!response.ok) {
        throw new Error(data.message || `Messages API error: ${response.status}`);
      }
      if (data.length > 0) {
        setMessage(data);
      }
      // Handle messages data as needed
    } catch (error) {
      setError(error.message);
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

     <section className={styles["msg-section"]}>
      
        <h2 className={styles["msg-section-title"]}>Conversations</h2>
        {conversations.length > 0 ? (
          <ul className={styles["msg-conversationsList"]}>
            {conversations.map((conv) => (
              <li key={conv.id} className={styles["msg-conversationItem"]}>
                <p><strong>ID:</strong> {conv.id}</p>
                <p><strong>Title:</strong> {conv.title}</p>
                <p><strong>Description:</strong> {conv.description}</p>
                <p><strong>Created By:</strong> {conv.createdBy}</p>
                <p><strong>Created At:</strong> {conv.createdAt}</p>
                <p><strong>Last Message At:</strong> {conv.lastMessageAt}</p>
              </li>
            ))}
          </ul>
        ) : (
          <p>No conversation data available.</p>
        )}
      </section>

      {/* === Messages Info === */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Messages</h2>
        {message.length > 0 ? (
          <ul className={styles["msg-messagesList"]}>
            {message.map((msg) => (
              <li key={msg.id} className={styles["msg-messageItem"]}>
                <p><strong>conversationTitle:</strong> {msg.conversationTitle}</p>
                <p>/</p>

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
