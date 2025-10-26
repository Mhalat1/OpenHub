import React, { useState, useEffect } from 'react';
import styles from '../style/Message.module.css';

const Messages = () => {
  const [userData, setUserData] = useState(null);
  const [friends, setFriends] = useState([]);
  const [conversation, setConversation] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

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
      if (data.length > 0) {
        setConversation(data[0]);
      }
    } catch (error) {
      setError(error.message);
    }
  };

  useEffect(() => {
    fetchData();
    fetchUserFriends();
    fetchConversations();
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

      {/* === Conversation Info === */}
      <section className={styles["msg-section"]}>
        <h2 className={styles["msg-section-title"]}>Conversation Info</h2>
        {conversation ? (
          <div className={styles["msg-conversation-card"]}>
            <p><strong>ID:</strong> {conversation.id}</p>
            <p><strong>Title:</strong> {conversation.title}</p>
            <p><strong>Description:</strong> {conversation.description}</p>
            <p><strong>Created By:</strong> {conversation.createdBy}</p>
            <p><strong>Created At:</strong> {conversation.createdAt}</p>
            <p><strong>Last Message At:</strong> {conversation.lastMessageAt}</p>
          </div>
        ) : (
          <p className={styles["msg-empty-message"]}>No conversation data available.</p>
        )}
      </section>
    </div>
  );
};

export default Messages;
