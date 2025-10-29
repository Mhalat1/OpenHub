import React, { useState, useEffect } from 'react';
import styles from '../style/Message.module.css';

const Messages = () => {
  // ========== STATES ==========
  const [userData, setUserData] = useState(null);
  const [friends, setFriends] = useState([]);
  const [conversations, setConversations] = useState([]);
  const [messages, setMessages] = useState([]);
  const [selectedConversationId, setSelectedConversationId] = useState(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [conv_users, setConv_users] = useState([]); // Tableau d'IDs

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [messageContents, setMessageContents] = useState({}); // Un contenu par conversation
  const [messageLoading, setMessageLoading] = useState(false);

  // ========== FETCH FUNCTIONS ==========
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
      setConversations(data);
      console.log(data);
    } catch (error) {
      setError(error.message);
    }
  };


  const handleDeleteConversation = async (conversationId) => {
    const token = localStorage.getItem("token");

    if (!window.confirm("Are you sure you want to delete this conversation?")) {
      return;
    }

    try {
      const response = await fetch(`http://127.0.0.1:8000/api/delete/conversation/${conversationId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (response.ok) {
        setSuccess(data.message || "Conversation deleted successfully!");
        setError(null);
        setConversations((prev) => prev.filter((c) => c.id !== conversationId));
      } else {
        setError(data.message || "Error deleting conversation");
        setSuccess(null);
      }

    } catch (error) {
      console.error("Error deleting conversation:", error);
    }
  };



  const handleDeleteMessage = async (messageId) => {
    const token = localStorage.getItem("token");

    if (!window.confirm("Are you sure you want to delete this message?")) return;

    try {
      const response = await fetch(`http://127.0.0.1:8000/api/delete/message/${messageId}`, {
        method: "DELETE",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (response.ok) {   

        setSuccess(data.message || "Message deleted successfully");
        setError(null);
        setMessages(prev => prev.filter(m => m.id !== messageId));
        setTimeout(() => setSuccess(null), 3000);

      } else {
        setError(data.message || "Error deleting message");
        setSuccess(null);
        //alert(data.message || "Error deleting message"); 
        // //alert() ouvre une modale en haut de la page web pour indiauer message d'erreure
      }
    } catch (error) {
      console.error("Error deleting message:", error);
      setError("Network error. Please try again.");
      setSuccess(null);
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
      setMessages(data || []);
    } catch (error) {
      setError(error.message);
    }
  };


  // Fonction de création
  const createConversation = async (title, description, conv_users) => {
    const token = localStorage.getItem("token");
    console.log("Sending conversation:", { title, description, conv_users });

    try {
      const response = await fetch("http://127.0.0.1:8000/api/create/conversation", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Authorization": `Bearer ${token}`,
        },
        body: JSON.stringify({ title, description, conv_users }), // Envoie le tableau
      });
      const data = await response.json();
      if (!response.ok) {
        throw new Error(data.message || `Create Conversation API error: ${response.status}`);
      }
      return data;
    } catch (error) {
      throw error;
    }
  };

  // Handler
  const handleCreateConversation = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    try {
      const data = await createConversation(title, description, conv_users);
      if (data) {
        setSuccess("Conversation created successfully!");
        setTitle("");
        setDescription("");
        setConv_users([]); // Reset le tableau
        await fetchConversations();
      }
    } catch (error) {
      setError(error.message);
    }
  };

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

  const handleCreateMessage = async (e, conversationId) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);
    setMessageLoading(true);

    const content = messageContents[conversationId] || "";

    try {
      const data = await createMessage(content, conversationId);
      if (data) {
        setSuccess("Message sent successfully!");
        // Clear only this conversation's message content
        setMessageContents(prev => ({
          ...prev,
          [conversationId]: ""
        }));
        await fetchMessages();
        await fetchConversations();
      }
    } catch (error) {
      if (error.message.includes('404') || error.message.includes('not found')) {
        setError(`Conversation does not exist.`);
      } else if (error.message.includes('401')) {
        setError('You must be logged in to create a message.');
      } else {
        setError(error.message);
      }
    } finally {
      setMessageLoading(false);
    }
  };

  const handleConversationClick = (convId) => {
    setSelectedConversationId(selectedConversationId === convId ? null : convId);
  };

  const handleMessageContentChange = (conversationId, value) => {
    setMessageContents(prev => ({
      ...prev,
      [conversationId]: value
    }));
  };

  // ========== COMPUTED DATA ==========
  const getMessagesForConversation = (conversationId) => {
    return messages.filter(msg => msg.conversationId === conversationId);
  };

  const getMessageCount = (conversationId) => {
    return getMessagesForConversation(conversationId).length;
  };

  // ========== EFFECTS ==========
  useEffect(() => {
    fetchData();
    fetchUserFriends();
    fetchConversations();
    fetchMessages();
  }, []);

  // ========== RENDER ==========
  if (loading) return <p className={styles["msg-loading"]}>Loading...</p>;

  return (
    <div className={styles["msg-page"]}>
      <div className={styles["msg-grid"]}>

        {/* LEFT COLUMN - User Info & Friends */}
        <aside className={styles["msg-sidebar"]}>

          {/* Friends List */}
          <section className={styles["msg-card"]}>
            <h2 className={styles["msg-card-title"]}>Friends ({friends.length})</h2>
            {friends.length > 0 ? (
              <ul className={styles["msg-list"]}>
                {friends.map((friend) => (
                  <li key={friend.id} className={styles["msg-friend-item"]}>
                    <div className={styles["msg-friend-name"]}>
                      {friend.firstName} {friend.lastName}
                    </div>
                    <div className={styles["msg-friend-email"]}>{friend.email}</div>
                  </li>
                ))}
              </ul>
            ) : (
              <p className={styles["msg-empty"]}>No friends</p>
            )}
          </section>
        </aside>

        {/* MIDDLE COLUMN - Conversations with Messages */}
        <main className={styles["msg-main"]}>
          <section className={styles["msg-card"]}>
            <h2 className={styles["msg-card-title"]}>Conversations ({conversations.length})</h2>


            {conversations.length > 0 ? (
              <div className={styles["msg-conversations-container"]}>
                {conversations.map((conv) => {
                  console.log(conv)
                  const conversationMessages = getMessagesForConversation(conv.id);
                  const isExpanded = selectedConversationId === conv.id;

                  return (
                    <div key={conv.id} className={styles["msg-conversation-wrapper"]}>
                      <div
                        className={`${styles["msg-conversation-item"]} ${isExpanded ? styles["msg-conversation-expanded"] : ""}`}
                        onClick={() => handleConversationClick(conv.id)}
                      >
                        <div className={styles["msg-conversation-header"]}>
                          <h3 className={styles["msg-conversation-title"]}>
                            {conv.title}
                            /
                            id : {conv.id}
                            /
                            nombres de messages :
                            <span className={styles["msg-message-count"]}>
                              {getMessageCount(conv.id)}
                            </span>
                            /

                          </h3>
                          <span className={styles["msg-conversation-date"]}>
                            {new Date(conv.createdAt).toLocaleDateString()}
                          </span>
                        </div>

                        <div className={styles.conversationActions}>
                          <button
                            className={styles.deleteBtn}
                            onClick={() => handleDeleteConversation(conv.id)}
                          >
                            🗑 Delete
                          </button>
                        </div>









                        <p className={styles["msg-conversation-description"]}>{conv.description}</p>
                        <div className={styles["msg-conversation-footer"]}>
                          {/* Créateur */}
                          <span className={styles["msg-conversation-author"]}>
                            By: {conv.createdBy}
                          </span>

                          {/* Utilisateurs de la conversation */}
                          {conv.users && conv.users.length > 0 && (
                            <span className={styles["msg-conversation-author"]}>
                              Participants ({conv.userCount}): {conv.users.map(u => u.name).join(', ')}
                            </span>
                          )}

                          {/* Dernier message */}
                          {conv.lastMessageAt && (
                            <span className={styles["msg-conversation-last"]}>
                              Last: {new Date(conv.lastMessageAt).toLocaleString()}
                            </span>
                          )}
                        </div>

                        <div className={styles["msg-expand-indicator"]}>
                          {isExpanded ? '▼ Hide messages' : '▶ Show messages'}
                        </div>
                      </div>

                      {/* Messages for this conversation */}
                      {isExpanded && (
                        <div className={styles["msg-messages-wrapper"]}>
                          {conversationMessages.length > 0 ? (
                            <ul className={styles["msg-list"]}>
                              {conversationMessages.map((msg) => (
                                <li key={msg.id} className={styles["msg-message-item"]}>
                                  <div className={styles["msg-message-header"]}>
                                    <span className={styles["msg-message-author"]}>{msg.authorName}</span>
                                    <span className={styles["msg-message-date"]}>
                                      {new Date(msg.createdAt).toLocaleString()}
                                    </span>


                                    <button
                                      className={styles.deleteBtn}
                                      onClick={(e) => {
                                        e.stopPropagation();
                                        handleDeleteMessage(msg.id);
                                      }}
                                    >
                                      🗑 Delete
                                    </button>

                                  </div>
                                  <p className={styles["msg-message-content"]}>{msg.content}</p>
                                </li>
                              ))}
                            </ul>
                          ) : (
                            <p className={styles["msg-empty"]}>No messages in this conversation yet</p>
                          )}



                          {/* Message Form inline in conversation */}
                          <form
                            onSubmit={(e) => handleCreateMessage(e, conv.id)}
                            className={styles["msg-form"]}
                            onClick={(e) => e.stopPropagation()}
                          >
                            <div className={styles["msg-form-group"]}>
                              <label className={styles["msg-form-label"]}>New Message</label>
                              <textarea
                                value={messageContents[conv.id] || ""}
                                onChange={(e) => handleMessageContentChange(conv.id, e.target.value)}
                                required
                                className={styles["msg-form-textarea"]}
                                placeholder="Type your message"
                                rows="3"
                              />
                            </div>
                            <button
                              type="submit"
                              className={styles["msg-form-button"]}
                              disabled={messageLoading}
                            >
                              {messageLoading ? "Sending..." : "Send Message"}
                            </button>
                          </form>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            ) : (
              <p className={styles["msg-empty"]}>No conversations</p>
            )}
          </section>
        </main>

        {/* RIGHT COLUMN - Forms */}
        <aside className={styles["msg-forms"]}>
          {/* Notifications */}
          {error && <div className={styles["msg-notification-error"]}>{error}</div>}
          {success && <div className={styles["msg-notification-success"]}>{success}</div>}
          {success && <div className={styles["msg-notification-success"]}>{success}</div>}

          <section className={styles["msg-card"]}>
            <h2 className={styles["msg-card-title"]}>New Conversation</h2>
            <form onSubmit={handleCreateConversation} className={styles["msg-form"]}>
              <div className={styles["msg-form-group"]}>
                <label className={styles["msg-form-label"]}>Title</label>
                <input
                  type="text"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  required
                  className={styles["msg-form-input"]}
                  placeholder="Enter conversation title"
                />
              </div>

              <div className={styles["msg-form-group"]}>
                <label className={styles["msg-form-label"]}>Description</label>
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  required
                  className={styles["msg-form-textarea"]}
                  placeholder="Describe the conversation"
                  rows="3"
                />
              </div>

              {/* Multi-select Friends */}
              <div className={styles["msg-form-group"]}>
                <label className={styles["msg-form-label"]}>
                  Select Friends ({conv_users.length} selected)
                </label>
                <div className={styles["msg-friends-list"]}>
                  {friends.length > 0 ? (
                    friends.map((friend) => (
                      <label key={friend.id} className={styles["msg-friend-checkbox"]}>
                        <input
                          type="checkbox"
                          checked={conv_users.includes(friend.id)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setConv_users([...conv_users, friend.id]);
                            } else {
                              setConv_users(conv_users.filter(id => id !== friend.id));
                            }
                          }}
                        />
                        <span>
                          {friend.firstName} {friend.lastName} ({friend.email})
                        </span>
                      </label>
                    ))
                  ) : (
                    <p className={styles["msg-empty"]}>No friends available</p>
                  )}
                </div>
              </div>

              <button type="submit" className={styles["msg-form-button"]}>
                Create Conversation
              </button>
            </form>
          </section>
        </aside>
      </div>
    </div>
  );
};

export default Messages;