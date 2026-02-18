import { useEffect, useState } from "react";
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
      // Check if token exists
      if (!token) {
        setNotif({ type: "error", text: "Not authenticated. Please log in." });
        setLoading(false);
        return;
      }

      const [userRes, friendsRes, convRes, msgRes] = await Promise.all([
        fetch(`${API_URL}/api/getConnectedUser`, { headers }),
        fetch(`${API_URL}/api/user/friends`, { headers }),
        fetch(`${API_URL}/api/get/conversations`, { headers }),
        fetch(`${API_URL}/api/get/messages`, { headers }),
      ]);

      // Check for authentication errors
      if (
        userRes.status === 401 ||
        friendsRes.status === 401 ||
        convRes.status === 401 ||
        msgRes.status === 401
      ) {
        setNotif({
          type: "error",
          text: "Session expired. Please log in again.",
        });
        localStorage.removeItem("token");
        setLoading(false);
        return;
      }

      const [u, f, c, msgData] = await Promise.all([
        userRes.json(),
        friendsRes.json(),
        convRes.json(),
        msgRes.json(),
      ]);

      setUser(u);
      setFriends(f);
      setConversations(c);

      // ✅ FIX: Handle paginated response structure
      setMessages(msgData.data || []); // Messages are in the 'data' property
    } catch (err) {
      console.error("Fetch error:", err);
      setNotif({ type: "error", text: "Error loading data: " + err.message });
    } finally {
      setLoading(false);
    }
  };

  // ====== ACTIONS ======
  const createConversation = async (e) => {
    e.preventDefault();

    // ✅ VALIDATION: Check user selection
    if (convUsers.length === 0) {
      setNotif({ type: "error", text: "Please select at least one friend." });
      return;
    }

    // ✅ VALIDATION: Title length
    if (newConv.title.length < 2 || newConv.title.length > 255) {
      setNotif({
        type: "error",
        text: "Title must be between 2 and 255 characters.",
      });
      return;
    }

    try {
      const res = await fetch(`${API_URL}/api/create/conversation`, {
        method: "POST",
        headers,
        body: JSON.stringify({
          title: newConv.title.trim(),
          description: newConv.description.trim(),
          conv_users: convUsers,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(
          data.message || data.error || "Failed to create conversation",
        );
      }

      setNotif({ type: "success", text: "Conversation created successfully!" });
      setNewConv({ title: "", description: "" });
      setConvUsers([]);
      await fetchData(); // Refresh data
    } catch (err) {
      console.error("Create conversation error:", err);
      setNotif({ type: "error", text: err.message });
    }
  };

  const sendMessage = async (e, convId) => {
    e.preventDefault();

    const content = newMsg[convId]?.trim();

    // ✅ VALIDATION: Empty message
    if (!content) {
      setNotif({ type: "error", text: "Message cannot be empty." });
      return;
    }

    // ✅ VALIDATION: Message length
    if (content.length > 250) {
      setNotif({
        type: "error",
        text: "Message cannot exceed 250 characters.",
      });
      return;
    }

    try {
      const res = await fetch(`${API_URL}/api/create/message`, {
        method: "POST",
        headers,
        body: JSON.stringify({
          content: content,
          conversation_id: convId,
        }),
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || data.error || "Failed to send message");
      }

      setNotif({ type: "success", text: "Message sent!" });
      setNewMsg({ ...newMsg, [convId]: "" });
      await fetchData(); // Refresh data
    } catch (err) {
      console.error("Send message error:", err);
      setNotif({ type: "error", text: err.message });
    }
  };

  const deleteConversation = async (id) => {
    if (!window.confirm("Delete this conversation?")) return;

    try {
      const res = await fetch(`${API_URL}/api/delete/conversation/${id}`, {
        method: "DELETE",
        headers,
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || "Failed to delete conversation");
      }

      setNotif({ type: "success", text: "Conversation deleted." });
      await fetchData();
    } catch (err) {
      console.error("Delete conversation error:", err);
      setNotif({ type: "error", text: err.message });
    }
  };

  const deleteMessage = async (id) => {
    if (!window.confirm("Delete this message?")) return;

    try {
      const res = await fetch(`${API_URL}/api/delete/message/${id}`, {
        method: "DELETE",
        headers,
      });

      const data = await res.json();

      if (!res.ok) {
        throw new Error(data.message || "Failed to delete message");
      }

      setNotif({ type: "success", text: "Message deleted." });
      await fetchData();
    } catch (err) {
      console.error("Delete message error:", err);
      setNotif({ type: "error", text: err.message });
    }
  };

  useEffect(() => {
    fetchData();
  }, []);

  // ✅ Auto-dismiss notifications after 5 seconds
  useEffect(() => {
    if (notif.text) {
      const timer = setTimeout(() => {
        setNotif({ type: "", text: "" });
      }, 5000);
      return () => clearTimeout(timer);
    }
  }, [notif]);

  if (loading) return <p className={styles.loading}>Loading...</p>;

  // ✅ Handle unauthenticated state
  if (!user) {
    return (
      <div className={styles.page}>
        <div className={styles.notification + " " + styles.error}>
          Please log in to view messages.
        </div>
      </div>
    );
  }

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
                  {f.firstName?.[0]}
                  {f.lastName?.[0]}
                </div>
                <div>
                  <strong>
                    {f.firstName} {f.lastName}
                  </strong>
                  <div className={styles.friendEmail}>{f.email}</div>
                </div>
              </li>
            ))}
          </ul>
        </aside>

        {/* ===== CONVERSATIONS ===== */}
        <main className={styles.main}>
          <h2 className={styles.sectionTitle}>
            Conversations ({conversations.length})
          </h2>

          {conversations.map((conv) => {
            const convMsgs = messages.filter(
              (m) => m.conversationId === conv.id,
            );
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
                    <span className={styles.toggle}>{isOpen ? "▲" : "▼"}</span>
                  </div>
                </div>

                {isOpen && (
                  <div className={styles.convBody}>
                    {convMsgs.length > 0 ? (
                      convMsgs.map((msg) => (
                        <div
                          key={msg.id}
                          className={`${styles.message} ${
                            msg.authorId === user?.id
                              ? styles.ownMsg
                              : styles.otherMsg
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
                        placeholder="Type your message (max 250 characters)..."
                        value={newMsg[conv.id] || ""}
                        onChange={(e) =>
                          setNewMsg({ ...newMsg, [conv.id]: e.target.value })
                        }
                        required
                        maxLength={250}
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
              placeholder="Title (2-255 characters)"
              value={newConv.title}
              onChange={(e) =>
                setNewConv({ ...newConv, title: e.target.value })
              }
              required
              minLength={2}
              maxLength={255}
              className={styles.input}
            />
            <textarea
              placeholder="Description (optional, max 1000 characters)"
              value={newConv.description}
              onChange={(e) =>
                setNewConv({ ...newConv, description: e.target.value })
              }
              maxLength={1000}
              className={styles.textarea}
            />
            <div className={styles.friendSelect}>
              <p>
                <strong>Select friends (at least 1):</strong>
              </p>
              {friends.length === 0 ? (
                <p>No friends available</p>
              ) : (
                friends.map((f) => (
                  <label key={f.id}>
                    <input
                      type="checkbox"
                      checked={convUsers.includes(f.id)}
                      onChange={(e) =>
                        setConvUsers(
                          e.target.checked
                            ? [...convUsers, f.id]
                            : convUsers.filter((id) => id !== f.id),
                        )
                      }
                    />
                    {f.firstName} {f.lastName}
                  </label>
                ))
              )}
            </div>
            <button
              className={styles.createBtn}
              disabled={convUsers.length === 0}
            >
              Create
            </button>
          </form>
        </aside>
      </div>
    </div>
  );
};

export default Messages;
