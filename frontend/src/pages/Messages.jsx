import React, { useState, useEffect } from "react";
import styles from "../style/Message.module.css";

const API_URL = import.meta.env.VITE_API_URL;

const Messages = () => {
  // ====== STATES ======
  const [user, setUser] = useState(null);
  const [friends, setFriends] = useState([]);
  const [conversations, setConversations] = useState([]);
  const [messages, setMessages] = useState([]);
  const [selectedConv, setSelectedConv] = useState(null);
  const [convUsers, setConvUsers] = useState([]);
  const [newConv, setNewConv] = useState({ title: "", description: "" });
  const [newMsg, setNewMsg] = useState({});
  const [loading, setLoading] = useState(true);
  const [notif, setNotif] = useState({ type: "", text: "" });

  // ====== FETCH HELPERS ======
  const token = localStorage.getItem("token");
  const headers = {
    "Content-Type": "application/json",
    Authorization: `Bearer ${token}`,
  };

  const fetchData = async () => {
    try {
      const [u, f, c, m] = await Promise.all([
        fetch(`${API_URL}/api/getConnectedUser`, { headers }).then((r) => r.json()),
        fetch(`${API_URL}/api/user/friends`, { headers }).then((r) => r.json()),
        fetch(`${API_URL}/api/get/conversations`, { headers }).then((r) => r.json()),
        fetch(`${API_URL}/api/get/messages`, { headers }).then((r) => r.json()),
      ]);
      setUser(u);
      setFriends(f);
      setConversations(c);
      setMessages(m);
    } catch (err) {
      setNotif({ type: "error", text: "Error loading data." });
    } finally {
      setLoading(false);
    }
  };

  // ====== ACTIONS ======
  const createConversation = async (e) => {
    e.preventDefault();
    try {
      const res = await fetch(`${API_URL}/api/create/conversation`, {
        method: "POST",
        headers,
        body: JSON.stringify({
          title: newConv.title,
          description: newConv.description,
          conv_users: convUsers,
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message);
      setNotif({ type: "success", text: "Conversation created successfully!" });
      setNewConv({ title: "", description: "" });
      setConvUsers([]);
      fetchData();
    } catch (err) {
      setNotif({ type: "error", text: err.message });
    }
  };

  const sendMessage = async (e, convId) => {
    e.preventDefault();
    try {
      const res = await fetch(`${API_URL}/api/create/message`, {
        method: "POST",
        headers,
        body: JSON.stringify({
          content: newMsg[convId],
          conversation_id: convId,
        }),
      });
      const data = await res.json();
      if (!res.ok) throw new Error(data.message);
      setNotif({ type: "success", text: "Message sent!" });
      setNewMsg({ ...newMsg, [convId]: "" });
      fetchData();
    } catch (err) {
      setNotif({ type: "error", text: err.message });
    }
  };

  const deleteConversation = async (id) => {
    if (!window.confirm("Delete this conversation?")) return;
    await fetch(`${API_URL}/api/delete/conversation/${id}`, {
      method: "DELETE",
      headers,
    });
    setNotif({ type: "success", text: "Conversation deleted." });
    fetchData();
  };

  const deleteMessage = async (id) => {
    if (!window.confirm("Delete this message?")) return;
    await fetch(`${API_URL}/api/delete/message/${id}`, {
      method: "DELETE",
      headers,
    });
    setNotif({ type: "success", text: "Message deleted." });
    fetchData();
  };

  useEffect(() => {
    fetchData();
  }, []);

  if (loading) return <p className={styles.loading}>Loading...</p>;

  // ====== UI ======
  return (
    <div className={styles.page}>
      <h1 className={styles.pageTitle}>Messages</h1>

      {notif.text && (
        <div
          className={`${styles.notification} ${
            notif.type === "error" ? styles.error : styles.success
          }`}
        >
          {notif.text}
        </div>
      )}

      <div className={styles.layout}>
        {/* ===== FRIENDS ===== */}
        <aside className={styles.sidebar}>
          <h2 className={styles.sectionTitle}>Friends ({friends.length})</h2>
          <ul className={styles.friendList}>
            {friends.map((f) => (
              <li key={f.id} className={styles.friendItem}>
                <div className={styles.avatar}>
                  {f.firstName[0]}
                  {f.lastName[0]}
                </div>
                <div>
                  <strong>{f.firstName} {f.lastName}</strong>
                  <div className={styles.friendEmail}>{f.email}</div>
                </div>
              </li>
            ))}
          </ul>
        </aside>

        {/* ===== CONVERSATIONS ===== */}
        <main className={styles.main}>
          <h2 className={styles.sectionTitle}>Conversations ({conversations.length})</h2>

          {conversations.map((conv) => {
            const convMsgs = messages.filter((m) => m.conversationId === conv.id);
            const isOpen = selectedConv === conv.id;

            return (
              <div key={conv.id} className={styles.conversation}>
                <div
                  className={styles.convHeader}
                  onClick={() => setSelectedConv(isOpen ? null : conv.id)}
                >
                  <div>
                    <h3 className={styles.convTitle}>{conv.title}</h3>
                    <p className={styles.convDesc}>{conv.description}</p>
                  </div>
                  <div className={styles.convActions}>
                    {conv.createdById === user?.id && (
                      <button
                        className={styles.deleteBtn}
                        onClick={(e) => {
                          e.stopPropagation();
                          deleteConversation(conv.id);
                        }}
                      >
                        🗑
                      </button>
                    )}
                    <span className={styles.toggle}>
                      {isOpen ? "▲" : "▼"}
                    </span>
                  </div>
                </div>

                {isOpen && (
                  <div className={styles.convBody}>
                    {convMsgs.length > 0 ? (
                      convMsgs.map((msg) => (
                        <div
                          key={msg.id}
                          className={`${styles.message} ${
                            msg.authorId === user?.id ? styles.ownMsg : styles.otherMsg
                          }`}
                        >
                          <div className={styles.msgHeader}>
                            <strong>{msg.authorName}</strong>
                            <span className={styles.msgDate}>
                              {new Date(msg.createdAt).toLocaleString()}
                            </span>
                            {msg.authorId === user?.id && (
                              <button
                                className={styles.msgDelete}
                                onClick={() => deleteMessage(msg.id)}
                              >
                                🗑
                              </button>
                            )}
                          </div>
                          <p>{msg.content}</p>
                        </div>
                      ))
                    ) : (
                      <p className={styles.empty}>No messages yet</p>
                    )}

                    <form
                      onSubmit={(e) => sendMessage(e, conv.id)}
                      className={styles.msgForm}
                    >
                      <textarea
                        className={styles.textarea}
                        placeholder="Type your message..."
                        value={newMsg[conv.id] || ""}
                        onChange={(e) =>
                          setNewMsg({ ...newMsg, [conv.id]: e.target.value })
                        }
                        required
                      />
                      <button className={styles.sendBtn}>Send</button>
                    </form>
                  </div>
                )}
              </div>
            );
          })}
        </main>

        {/* ===== CREATE CONVERSATION ===== */}
        <aside className={styles.sidebar}>
          <h2 className={styles.sectionTitle}>New Conversation</h2>
          <form onSubmit={createConversation} className={styles.form}>
            <input
              type="text"
              placeholder="Title"
              value={newConv.title}
              onChange={(e) =>
                setNewConv({ ...newConv, title: e.target.value })
              }
              required
              className={styles.input}
            />
            <textarea
              placeholder="Description"
              value={newConv.description}
              onChange={(e) =>
                setNewConv({ ...newConv, description: e.target.value })
              }
              required
              className={styles.textarea}
            />
            <div className={styles.friendSelect}>
              {friends.map((f) => (
                <label key={f.id}>
                  <input
                    type="checkbox"
                    checked={convUsers.includes(f.id)}
                    onChange={(e) =>
                      setConvUsers(
                        e.target.checked
                          ? [...convUsers, f.id]
                          : convUsers.filter((id) => id !== f.id)
                      )
                    }
                  />
                  {f.firstName} {f.lastName}
                </label>
              ))}
            </div>
            <button className={styles.createBtn}>Create</button>
          </form>
        </aside>
      </div>
    </div>
  );
};

export default Messages;
