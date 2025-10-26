import React, { useState, useEffect, useRef } from 'react';
import styles from '../style/Message.module.css';

const Messages = () => {
  const [userData, setUserData] = useState(null);
  const [conversationId, setConversationId] = useState(null);
  const [messages, setMessages] = useState([]);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [friends, setFriends] = useState([]);

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
      console.log('User Response:', data);

      if (!userResponse.ok) {
        throw new Error(data.message || `User API error: ${userResponse.status}`);
      }
      setUserData(data);
      console.log('User Data:', data);
    } catch (error) {
      console.error('Error fetching user:', error);
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
    } finally {
      setLoading(false);
    }
  };


  useEffect(() => {
    fetchData();
    fetchUserFriends();
  }, []);


  return (
    <div className={styles.connectedUserContainer}>
      <h2>Connected User Information</h2>
      <h2>Connected User Information</h2>
      {userData ? (
        <div className={styles.userInfo}>
          <p><strong>ID:</strong> {userData.id}</p>
          <p><strong>Email:</strong> {userData.email}</p>
          <p><strong>First Name:</strong> {userData.firstName}</p>
          <p><strong>Last Name:</strong> {userData.lastName}</p>
          <p><strong>Availability Start:</strong> {userData.availabilityStart || 'N/A'}</p>
          <p><strong>Availability End:</strong> {userData.availabilityEnd || 'N/A'}</p>

        </div>
      ) : (
        <p>No user data available.</p>
      )}
      <h2>user friends</h2>
      {friends.length > 0 ? (
        <ul className={styles.friendsList}>
          {friends.map((friend) => (
            <li key={friend.id} className={styles.friendItem}>
              <p><strong>ID:</strong> {friend.id}</p>
              <p><strong>Email:</strong> {friend.email}</p>
              <p><strong>First Name:</strong> {friend.firstName}</p>
              <p><strong>Last Name:</strong> {friend.lastName}</p>
            </li>
          ))}
        </ul>
      ) : (
        <p>No friends data available.</p>
      )}


    </div>  
  );
};

export default Messages;