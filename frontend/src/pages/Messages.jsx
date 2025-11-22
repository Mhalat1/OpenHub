import React, { useState, useEffect, useRef } from 'react';
import styles from '../style/Message.module.css';

const API_URL = import.meta.env.VITE_API_URL;

const Messages = () => {
  // ========== STATES ==========
  const [userData, setUserData] = useState(null);
  const [friends, setFriends] = useState([]);
  const [conversations, setConversations] = useState([]);
  const [messages, setMessages] = useState([]);
  const [selectedConversationId, setSelectedConversationId] = useState(null);
  const [activeSection, setActiveSection] = useState('conversations'); // 'conversations' | 'friends'

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [success, setSuccess] = useState(null);
  const [conv_users, setConv_users] = useState([]);

  const [title, setTitle] = useState("");
  const [description, setDescription] = useState("");
  const [messageContents, setMessageContents] = useState({});
  const [messageLoading, setMessageLoading] = useState(false);
  const [connectedUser, setConnectedUser] = useState(null);
  const [actionLoading, setActionLoading] = useState({ type: '', id: null });

  const messagesEndRef = useRef(null);

  // ========== FETCH FUNCTIONS ==========
  const fetchData = async () => {
    const token = localStorage.getItem("token");
    if (!token) {
      setError("No authentication token found");
      setLoading(false);
      return;
    }

    try {
      const [userResponse, friendsResponse, conversationsResponse, messagesResponse] = await Promise.all([
        fetch(`${API_URL}/api/getConnectedUser`, {
          headers: { "Authorization": `Bearer ${token}` },
        }),
        fetch(`${API_URL}/api/user/friends`, {
          headers: { "Authorization": `Bearer ${token}` },
        }),
        fetch(`${API_URL}/api/get/conversations`, {
          headers: { "Authorization": `Bearer ${token}` },
        }),
        fetch(`${API_URL}/api/get/messages`, {
          headers: { "Authorization": `Bearer ${token}` },
        })
      ]);

      const userData = await userResponse.json();
      if (!userResponse.ok) throw new Error(userData.message || `User API error: ${userResponse.status}`);
      
      const friendsData = await friendsResponse.json();
      const conversationsData = await conversationsResponse.json();
      const messagesData = await messagesResponse.json();

      setUserData(userData);
      setConnectedUser(userData);
      setFriends(friendsData);
      setConversations(conversationsData);
      setMessages(messagesData || []);

    } catch (error) {
      setError(error.message);
    } finally {
      setLoading(false);
    }
  };

  // ========== CONVERSATION FUNCTIONS ==========
  const handleDeleteConversation = async (conversationId) => {
    if (!window.confirm("Are you sure you want to delete this conversation? All messages will be lost.")) {
      return;
    }

    setActionLoading({ type: 'deleteConversation', id: conversationId });
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(`${API_URL}/api/delete/conversation/${conversationId}`, {
        method: "DELETE",
        headers: { "Authorization": `Bearer ${token}` },
      });

      const data = await response.json();

      if (response.ok) {
        setSuccess(data.message || "Conversation deleted successfully!");
        setConversations((prev) => prev.filter((c) => c.id !== conversationId));
        if (selectedConversationId === conversationId) {
          setSelectedConversationId(null);
        }
      } else {
        setError(data.message || "Error deleting conversation");
      }
    } catch (error) {
      console.error("Error deleting conversation:", error);
      setError("Network error. Please try again.");
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  const createConversation = async (title, description, conv_users) => {
    const token = localStorage.getItem("token");
    
    try {
      const response = await fetch(`${API_URL}/api/create/conversation`, {
        method: "POST",
        headers: { "Authorization": `Bearer ${token}` },
        body: JSON.stringify({ title, description, conv_users }),
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

  const handleCreateConversation = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccess(null);

    if (!title.trim() || !description.trim()) {
      setError("Title and description are required");
      return;
    }

    if (conv_users.length === 0) {
      setError("Please select at least one friend");
      return;
    }

    setActionLoading({ type: 'createConversation', id: null });

    try {
      const data = await createConversation(title, description, conv_users);
      setSuccess("Conversation created successfully!");
      setTitle("");
      setDescription("");
      setConv_users([]);
      await fetchData();
      setActiveSection('conversations');
    } catch (error) {
      setError(error.message);
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  // ========== MESSAGE FUNCTIONS ==========
  const handleDeleteMessage = async (messageId) => {
    if (!window.confirm("Are you sure you want to delete this message?")) return;

    setActionLoading({ type: 'deleteMessage', id: messageId });
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(`${API_URL}/api/delete/message/${messageId}`, {
        method: "DELETE",
        headers: { "Authorization": `Bearer ${token}` },
      });

      const data = await response.json();

      if (response.ok) {   
        setSuccess(data.message || "Message deleted successfully");
        setMessages(prev => prev.filter(m => m.id !== messageId));
      } else {
        setError(data.message || "Error deleting message");
      }
    } catch (error) {
      console.error("Error deleting message:", error);
      setError("Network error. Please try again.");
    } finally {
      setActionLoading({ type: '', id: null });
    }
  };

  const createMessage = async (content, conversation_id) => {
    const token = localStorage.getItem("token");
    
    try {
      const response = await fetch(`${API_URL}/api/create/message`, {
        method: "POST",
        headers: { "Authorization": `Bearer ${token}` },
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
    
    const content = messageContents[conversationId]?.trim() || "";
    
    if (!content) {
      setError("Message content cannot be empty");
      return;
    }

    setMessageLoading(true);

    try {
      const data = await createMessage(content, conversationId);
      setSuccess("Message sent successfully!");
      
      setMessageContents(prev => ({
        ...prev,
        [conversationId]: ""
      }));
      
      await fetchData();
      
      // Scroll to bottom after sending message
      setTimeout(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
      }, 100);
      
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

  // ========== UI HANDLERS ==========
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
    return messages
      .filter(msg => msg.conversationId === conversationId)
      .sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
  };

  const getMessageCount = (conversationId) => {
    return getMessagesForConversation(conversationId).length;
  };

  const getSelectedConversation = () => {
    return conversations.find(conv => conv.id === selectedConversationId);
  };

  // ========== EFFECTS ==========
  useEffect(() => {
    fetchData();
  }, []);

  useEffect(() => {
    if (success || error) {
      const timer = setTimeout(() => {
        setSuccess(null);
        setError(null);
      }, 4000);
      return () => clearTimeout(timer);
    }
  }, [success, error]);

  useEffect(() => {
    // Auto-scroll to bottom when messages change
    messagesEndRef.current?.scrollIntoView({ behavior: "smooth" });
  }, [messages, selectedConversationId]);

  // ========== RENDER ==========
  if (loading) return (
    <div className={styles.loadingContainer}>
      <div className={styles.spinner}></div>
      <p>Loading messages...</p>
    </div>
  );

  return (
    <div className={styles.messagesPage}>
      {/* Header */}
      <div className={styles.header}>
        <h1 className={styles.pageTitle}>💬 Messages</h1>
        <div className={styles.headerActions}>
          <button 
            className={`${styles.headerButton} ${activeSection === 'conversations' ? styles.active : ''}`}
            onClick={() => setActiveSection('conversations')}
          >
            📁 Conversations ({conversations.length})
          </button>
          <button 
            className={`${styles.headerButton} ${activeSection === 'friends' ? styles.active : ''}`}
            onClick={() => setActiveSection('friends')}
          >
            👥 Friends ({friends.length})
          </button>
        </div>
      </div>

      {/* Notifications */}
      <div className={styles.notifications}>
        {error && (
          <div className={styles.notificationError}>
            <span>⚠️</span>
            {error}
          </div>
        )}
        {success && (
          <div className={styles.notificationSuccess}>
            <span>✅</span>
            {success}
          </div>
        )}
      </div>

      <div className={styles.mainLayout}>
        {/* Left Sidebar - Conversations List */}
        <aside className={styles.sidebar}>
          <div className={styles.sidebarSection}>
            <h2 className={styles.sectionTitle}>
              {activeSection === 'conversations' ? '📁 Conversations' : '👥 Friends'}
            </h2>
            
            {activeSection === 'conversations' ? (
              <div className={styles.conversationsList}>
                {conversations.length > 0 ? (
                  conversations.map((conv) => (
                    <div
                      key={conv.id}
                      className={`${styles.conversationItem} ${
                        selectedConversationId === conv.id ? styles.active : ''
                      }`}
                      onClick={() => handleConversationClick(conv.id)}
                    >
                      <div className={styles.conversationHeader}>
                        <h3 className={styles.conversationName}>
                          {conv.title}
                        </h3>
                        <span className={styles.messageCount}>
                          {getMessageCount(conv.id)}
                        </span>
                      </div>
                      
                      <p className={styles.conversationPreview}>
                        {conv.description}
                      </p>
                      
                      <div className={styles.conversationMeta}>
                        <span className={styles.participants}>
                          👥 {conv.userCount || 1}
                        </span>
                        <span className={styles.lastActivity}>
                          {conv.lastMessageAt 
                            ? new Date(conv.lastMessageAt).toLocaleDateString()
                            : new Date(conv.createdAt).toLocaleDateString()
                          }
                        </span>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className={styles.emptyState}>
                    <p>No conversations yet</p>
                    <p className={styles.emptySubtitle}>Start a new conversation with friends</p>
                  </div>
                )}
              </div>
            ) : (
              <div className={styles.friendsList}>
                {friends.length > 0 ? (
                  friends.map((friend) => (
                    <div key={friend.id} className={styles.friendItem}>
                      <div className={styles.friendAvatar}>
                        {friend.firstName?.charAt(0)}{friend.lastName?.charAt(0)}
                      </div>
                      <div className={styles.friendInfo}>
                        <h4 className={styles.friendName}>
                          {friend.firstName} {friend.lastName}
                        </h4>
                        <p className={styles.friendEmail}>{friend.email}</p>
                      </div>
                    </div>
                  ))
                ) : (
                  <div className={styles.emptyState}>
                    <p>No friends yet</p>
                    <p className={styles.emptySubtitle}>Add friends to start messaging</p>
                  </div>
                )}
              </div>
            )}
          </div>
        </aside>

        {/* Main Content - Chat Area */}
        <main className={styles.chatArea}>
          {selectedConversationId ? (
            <div className={styles.chatContainer}>
              {/* Chat Header */}
              <div className={styles.chatHeader}>
                {(() => {
                  const conversation = getSelectedConversation();
                  return (
                    <>
                      <div className={styles.chatInfo}>
                        <h2 className={styles.chatTitle}>{conversation?.title}</h2>
                        <p className={styles.chatDescription}>{conversation?.description}</p>
                        <div className={styles.chatMeta}>
                          <span className={styles.participantCount}>
                            👥 {conversation?.userCount || 1} participants
                          </span>
                          {conversation?.createdBy && (
                            <span className={styles.createdBy}>
                              Created by: {conversation.createdBy}
                            </span>
                          )}
                        </div>
                      </div>
                      
                      <div className={styles.chatActions}>
                        {conversation?.createdById === connectedUser?.id && (
                          <button
                            className={styles.deleteButton}
                            onClick={() => handleDeleteConversation(selectedConversationId)}
                            disabled={actionLoading.type === 'deleteConversation' && actionLoading.id === selectedConversationId}
                          >
                            {actionLoading.type === 'deleteConversation' && actionLoading.id === selectedConversationId ? (
                              <span className={styles.spinner}></span>
                            ) : (
                              '🗑 Delete'
                            )}
                          </button>
                        )}
                      </div>
                    </>
                  );
                })()}
              </div>

              {/* Messages List */}
              <div className={styles.messagesList}>
                {getMessagesForConversation(selectedConversationId).length > 0 ? (
                  <>
                    {getMessagesForConversation(selectedConversationId).map((msg) => (
                      <div
                        key={msg.id}
                        className={`${styles.messageBubble} ${
                          msg.authorId === connectedUser?.id ? styles.ownMessage : styles.otherMessage
                        }`}
                      >
                        <div className={styles.messageHeader}>
                          <span className={styles.messageAuthor}>
                            {msg.authorName}
                            {msg.authorId === connectedUser?.id && ' (You)'}
                          </span>
                          <span className={styles.messageTime}>
                            {new Date(msg.createdAt).toLocaleTimeString([], { 
                              hour: '2-digit', 
                              minute: '2-digit' 
                            })}
                          </span>
                          
                          {msg.authorId === connectedUser?.id && (
                            <button
                              className={styles.messageDelete}
                              onClick={() => handleDeleteMessage(msg.id)}
                              disabled={actionLoading.type === 'deleteMessage' && actionLoading.id === msg.id}
                              title="Delete message"
                            >
                              {actionLoading.type === 'deleteMessage' && actionLoading.id === msg.id ? (
                                <span className={styles.spinner}></span>
                              ) : (
                                '🗑'
                              )}
                            </button>
                          )}
                        </div>
                        
                        <div className={styles.messageContent}>
                          {msg.content}
                        </div>
                        
                        <div className={styles.messageDate}>
                          {new Date(msg.createdAt).toLocaleDateString()}
                        </div>
                      </div>
                    ))}
                    <div ref={messagesEndRef} />
                  </>
                ) : (
                  <div className={styles.emptyChat}>
                    <p>No messages yet</p>
                    <p className={styles.emptySubtitle}>Start the conversation!</p>
                  </div>
                )}
              </div>

              {/* Message Input */}
              <form
                onSubmit={(e) => handleCreateMessage(e, selectedConversationId)}
                className={styles.messageForm}
              >
                <div className={styles.inputGroup}>
                  <textarea
                    value={messageContents[selectedConversationId] || ""}
                    onChange={(e) => handleMessageContentChange(selectedConversationId, e.target.value)}
                    placeholder="Type your message..."
                    className={styles.messageInput}
                    rows="2"
                    disabled={messageLoading}
                  />
                  <button
                    type="submit"
                    className={styles.sendButton}
                    disabled={messageLoading || !messageContents[selectedConversationId]?.trim()}
                  >
                    {messageLoading ? (
                      <span className={styles.spinner}></span>
                    ) : (
                      '📤 Send'
                    )}
                  </button>
                </div>
              </form>
            </div>
          ) : (
            <div className={styles.noConversation}>
              <div className={styles.noConversationContent}>
                <h2>💬 Welcome to Messages</h2>
                <p>Select a conversation from the sidebar or create a new one to start messaging.</p>
                <button 
                  onClick={() => setActiveSection('conversations')}
                  className={styles.primaryButton}
                >
                  📁 View Conversations
                </button>
              </div>
            </div>
          )}
        </main>

        {/* Right Sidebar - New Conversation Form */}
        <aside className={styles.createSidebar}>
          <div className={styles.sidebarSection}>
            <h2 className={styles.sectionTitle}>✨ New Conversation</h2>
            
            <form onSubmit={handleCreateConversation} className={styles.conversationForm}>
              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Conversation Title</label>
                <input
                  type="text"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  required
                  className={styles.formInput}
                  placeholder="Enter conversation title"
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>Description</label>
                <textarea
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  required
                  className={styles.formTextarea}
                  placeholder="Describe the conversation"
                  rows="3"
                />
              </div>

              <div className={styles.formGroup}>
                <label className={styles.formLabel}>
                  Add Friends ({conv_users.length} selected)
                </label>
                <div className={styles.friendsSelector}>
                  {friends.length > 0 ? (
                    friends.map((friend) => (
                      <label key={friend.id} className={styles.friendOption}>
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
                        <span className={styles.friendCheckbox}>
                          <span className={styles.friendAvatarSmall}>
                            {friend.firstName?.charAt(0)}{friend.lastName?.charAt(0)}
                          </span>
                          <span className={styles.friendInfoSmall}>
                            <strong>{friend.firstName} {friend.lastName}</strong>
                            <span>{friend.email}</span>
                          </span>
                        </span>
                      </label>
                    ))
                  ) : (
                    <p className={styles.emptyState}>No friends available</p>
                  )}
                </div>
              </div>

              <button 
                type="submit" 
                className={styles.primaryButton}
                disabled={actionLoading.type === 'createConversation'}
              >
                {actionLoading.type === 'createConversation' ? (
                  <>
                    <span className={styles.spinner}></span>
                    Creating...
                  </>
                ) : (
                  '✨ Create Conversation'
                )}
              </button>
            </form>
          </div>
        </aside>
      </div>
    </div>
  );
};

export default Messages;