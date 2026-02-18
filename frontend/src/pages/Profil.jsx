import React, { useEffect, useState } from "react";
import styles from "../style/Profil.module.css";

const API_URL = import.meta.env.VITE_API_URL;

const Profil = () => {
  const [users, setUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState("public");
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedUser, setSelectedUser] = useState(null);
  const [skills, setSkills] = useState([]);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [userProjects, setUserProjects] = useState([]);
  const [friends, setFriends] = useState([]);
  const [connectedUser, setUser] = useState([]);
  const [notification, setNotification] = useState({ message: "", type: "" });

  const [sentInvitations, setSentInvitations] = useState([]);
  const [receivedInvitations, setReceivedInvitations] = useState([]);
  const [actionLoading, setActionLoading] = useState({ type: "", id: null });

  const fetchAllUsers = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch(`${API_URL}/api/getAllUsers`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
      });
      const data = await response.json();

      const uniqueUsers = Array.from(
        new Map(data.map((user) => [user.id, user])).values(),
      );

      setUsers(uniqueUsers);
    } catch (error) {
      setError(error.message);
    }
  };

  const fetchReceivedInvitations = async () => {
    const token = localStorage.getItem("token");
    try {
      const res = await fetch(`${API_URL}/api/invitations/received`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const data = await res.json();
      setReceivedInvitations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error("Error fetching received invitations:", error);
      setNotification({
        message: "❌ Impossible de récupérer les invitations.",
        type: "error",
      });
    }
  };

  const fetchSentInvitations = async () => {
    const token = localStorage.getItem("token");
    try {
      const res = await fetch(`${API_URL}/api/invitations/sent`, {
        headers: { Authorization: `Bearer ${token}` },
      });
      const data = await res.json();
      setSentInvitations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error("Error fetching sent invitations:", error);
    }
  };

  const fetchUserFriends = async () => {
    const token = localStorage.getItem("token");
    try {
      const response = await fetch(`${API_URL}/api/user/friends`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
      });
      const data = await response.json();
      setFriends(data);
    } catch (error) {
      setError(error.message);
    }
  };

  const deleteFriend = async (friendId) => {
    if (!window.confirm("Êtes-vous sûr de vouloir supprimer cet ami ?")) {
      return;
    }

    setActionLoading({ type: "deleteFriend", id: friendId });
    const token = localStorage.getItem("token");

    try {
      const response = await fetch(
        `${API_URL}/api/delete/friends/${friendId}`,
        {
          method: "DELETE",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        },
      );

      const data = await response.json();
      if (response.ok) {
        await fetchUserFriends();
        setNotification({
          message: "✅ Ami supprimé avec succès !",
          type: "success",
        });
      } else {
        setNotification({ message: `❌ ${data.message}`, type: "error" });
      }
    } catch (error) {
      console.error("Erreur lors de la suppression :", error);
      setNotification({ message: "❌ Erreur réseau", type: "error" });
    } finally {
      setActionLoading({ type: "", id: null });
    }
  };

  const sendInvitation = async (friend_id) => {
    const token = localStorage.getItem("token");
    if (!token) return;

    setActionLoading({ type: "sendInvitation", id: friend_id });

    try {
      const response = await fetch(`${API_URL}/api/send/invitation`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({ friend_id }),
      });

      const data = await response.json();

      if (!response.ok) {
        setNotification({
          message: data.message || "❌ Erreur lors de l'envoi de l'invitation.",
          type: "error",
        });
        return;
      }

      if (response.ok) {
        setNotification({
          message: "✅ Invitation envoyée avec succès !",
          type: "success",
        });
        await fetchSentInvitations();
        handleCloseModal();
      }
    } catch (error) {
      setNotification({
        message: "❌ Erreur réseau : impossible d'envoyer l'invitation.",
        type: "error",
      });
      console.error("Error adding friend:", error);
    } finally {
      setActionLoading({ type: "", id: null });
    }
  };

  const fetchConnectedUser = async () => {
    const token = localStorage.getItem("token");

    try {
      const userResponse = await fetch(`${API_URL}/api/getConnectedUser`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
      });

      if (!userResponse.ok) {
        throw new Error(`User API error: ${userResponse.status}`);
      }

      const dataUser = await userResponse.json();
      setUser(dataUser);
    } catch (error) {
      console.error("Error fetching user:", error);
      setError(error.message);
    }
  };

  const deleteReceivedInvitation = async (senderId) => {
    const token = localStorage.getItem("token");
    if (!token) return;

    setActionLoading({ type: "deleteReceived", id: senderId });

    try {
      const response = await fetch(
        `${API_URL}/api/invitations/delete-received/${senderId}`,
        {
          method: "DELETE",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        },
      );

      const data = await response.json();

      if (response.ok && data.success) {
        setNotification({
          message: "✅ Invitation supprimée avec succès.",
          type: "success",
        });
        await fetchReceivedInvitations();
      } else {
        setNotification({
          message: `❌ ${data.message || "Erreur lors de la suppression."}`,
          type: "error",
        });
      }
    } catch (error) {
      console.error("Erreur lors de la suppression :", error);
      setNotification({ message: "❌ Erreur réseau.", type: "error" });
    } finally {
      setActionLoading({ type: "", id: null });
    }
  };

  const deleteSentInvitation = async (receiverId) => {
    const token = localStorage.getItem("token");
    if (!token) return;

    setActionLoading({ type: "deleteSent", id: receiverId });

    try {
      const response = await fetch(
        `${API_URL}/api/invitations/delete-sent/${receiverId}`,
        {
          method: "DELETE",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        },
      );

      const data = await response.json();

      if (response.ok && data.success) {
        setNotification({
          message: "✅ Invitation envoyée supprimée avec succès.",
          type: "success",
        });
        await fetchSentInvitations();
      } else {
        setNotification({
          message: `❌ ${data.message || "Erreur lors de la suppression."}`,
          type: "error",
        });
      }
    } catch (error) {
      console.error("Erreur lors de la suppression :", error);
      setNotification({ message: "❌ Erreur réseau.", type: "error" });
    } finally {
      setActionLoading({ type: "", id: null });
    }
  };

  const acceptInvitation = async (senderId) => {
    const token = localStorage.getItem("token");
    if (!token) return;

    setActionLoading({ type: "acceptInvitation", id: senderId });

    try {
      const response = await fetch(
        `${API_URL}/api/invitations/accept/${senderId}`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        },
      );

      const data = await response.json();

      if (response.ok && data.success) {
        setNotification({ message: data.message, type: "success" });
        await fetchReceivedInvitations();
        await fetchUserFriends();
      } else {
        setNotification({
          message: data.message || "Erreur lors de l'acceptation",
          type: "error",
        });
      }
    } catch (error) {
      setNotification({ message: "Erreur réseau", type: "error" });
    } finally {
      setActionLoading({ type: "", id: null });
    }
  };

  useEffect(() => {
    const init = async () => {
      setLoading(true);
      await fetchConnectedUser();
      await fetchAllUsers();
      await fetchUserFriends();
      await fetchReceivedInvitations();
      await fetchSentInvitations();
      setLoading(false);
    };
    init();
  }, []);

  useEffect(() => {
    if (notification.message) {
      const timer = setTimeout(() => {
        setNotification({ message: "", type: "" });
      }, 4000);
      return () => clearTimeout(timer);
    }
  }, [notification]);

  const filteredUsers = users.filter((user) => {
    const matchesSearch =
      `${user.firstName} ${user.lastName}`
        .toLowerCase()
        .includes(searchTerm.toLowerCase()) ||
      user.email.toLowerCase().includes(searchTerm.toLowerCase());

    const isNotCurrentUser = connectedUser && user.id !== connectedUser.id;

    return matchesSearch && isNotCurrentUser;
  });

  const handleOpenModal = (user) => {
    setSelectedUser(user);
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSelectedUser(null);
  };

  if (loading)
    return (
      <div className={styles.loadingContainer}>
        <div className={styles.spinner}></div>
        <p>Chargement des données...</p>
      </div>
    );

  if (error)
    return (
      <div className={styles.errorContainer}>
        <p>Erreur: {error}</p>
      </div>
    );

  return (
    <div className={styles.profileContainer}>
      {/* Header with search bar */}
      <div className={styles.header}>
        <h1 className={styles.pageTitle}>Réseau Social</h1>
        <div className={styles.searchContainer}>
          <input
            type="text"
            placeholder="Filtrer les utilisateurs..."
            className={styles.searchInput}
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
          />
        </div>
      </div>

      {/* Notification */}
      {notification.message && (
        <div className={`${styles.notification} ${styles[notification.type]}`}>
          {notification.message}
        </div>
      )}

      {/* Sub-navigation */}
      <div className={styles.subNav}>
        <button
          className={`${styles.subNavButton} ${activeTab === "public" ? styles.active : ""}`}
          onClick={() => setActiveTab("public")}
        >
          🌍 Utilisateurs Publics
        </button>
        <button
          className={`${styles.subNavButton} ${activeTab === "friends" ? styles.active : ""}`}
          onClick={() => setActiveTab("friends")}
        >
          👥 Amis ({friends.length})
        </button>
        <button
          className={`${styles.subNavButton} ${activeTab === "sent" ? styles.active : ""}`}
          onClick={() => setActiveTab("sent")}
        >
          📤 Invitations Envoyées ({sentInvitations.length})
        </button>
        <button
          className={`${styles.subNavButton} ${activeTab === "received" ? styles.active : ""}`}
          onClick={() => setActiveTab("received")}
        >
          📥 Invitations Reçues ({receivedInvitations.length})
        </button>
      </div>

      {/* Content Sections */}
      <div className={styles.contentSection}>
        {/* Public Users */}
        {activeTab === "public" && (
          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>🌍 Utilisateurs Publics</h2>
            {filteredUsers.length === 0 ? (
              <div className={styles.emptyState}>
                <p>Aucun utilisateur trouvé</p>
              </div>
            ) : (
              <div className={styles.usersGrid}>
                {filteredUsers.map((user) => (
                  <div
                    key={user.id}
                    className={styles.userCard}
                    onClick={() => handleOpenModal(user)}
                  >
                    <div className={styles.userAvatar}>
                      {user.firstName?.charAt(0)}
                      {user.lastName?.charAt(0)}
                    </div>
                    <div className={styles.userInfo}>
                      <h3 className={styles.userName}>
                        {user.firstName} {user.lastName}
                      </h3>
                      <p className={styles.userEmail}>{user.email}</p>
                    </div>
                    <div className={styles.userAction}>
                      <button className={styles.addFriendBtn}>+</button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Friends */}
        {activeTab === "friends" && (
          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>👥 Mes Amis</h2>
            {friends.length === 0 ? (
              <div className={styles.emptyState}>
                <p>Aucun ami pour le moment</p>
                <p className={styles.emptyStateSubtitle}>
                  Ajoutez des amis en envoyant des invitations
                </p>
              </div>
            ) : (
              <div className={styles.friendsGrid}>
                {friends.map((friend) => (
                  <div key={friend.id} className={styles.friendCard}>
                    <div className={styles.friendAvatar}>
                      {friend.firstName?.charAt(0)}
                      {friend.lastName?.charAt(0)}
                    </div>
                    <div className={styles.friendInfo}>
                      <h4 className={styles.friendName}>
                        {friend.firstName} {friend.lastName}
                      </h4>
                      <p className={styles.friendEmail}>{friend.email}</p>
                    </div>
                    <div className={styles.friendActions}>
                      <button
                        className={styles.unfriendBtn}
                        onClick={() => deleteFriend(friend.id)}
                        disabled={
                          actionLoading.type === "deleteFriend" &&
                          actionLoading.id === friend.id
                        }
                      >
                        {actionLoading.type === "deleteFriend" &&
                        actionLoading.id === friend.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          "❌ Supprimer"
                        )}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Sent Invitations */}
        {activeTab === "sent" && (
          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>📤 Invitations Envoyées</h2>
            {sentInvitations.length === 0 ? (
              <div className={styles.emptyState}>
                <p>Aucune invitation envoyée</p>
              </div>
            ) : (
              <div className={styles.invitationsGrid}>
                {sentInvitations.map((inv) => (
                  <div key={inv.id} className={styles.invitationCard}>
                    <div className={styles.invitationAvatar}>
                      {inv.firstName?.charAt(0)}
                      {inv.lastName?.charAt(0)}
                    </div>
                    <div className={styles.invitationInfo}>
                      <h4>
                        {inv.firstName} {inv.lastName}
                      </h4>
                      <p>{inv.email}</p>
                    </div>
                    <div className={styles.invitationActions}>
                      <button
                        className={styles.cancelBtn}
                        onClick={() => deleteSentInvitation(inv.id)}
                        disabled={
                          actionLoading.type === "deleteSent" &&
                          actionLoading.id === inv.id
                        }
                      >
                        {actionLoading.type === "deleteSent" &&
                        actionLoading.id === inv.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          "Annuler"
                        )}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Received Invitations */}
        {activeTab === "received" && (
          <div className={styles.section}>
            <h2 className={styles.sectionTitle}>📥 Invitations Reçues</h2>
            {receivedInvitations.length === 0 ? (
              <div className={styles.emptyState}>
                <p>Aucune invitation reçue</p>
              </div>
            ) : (
              <div className={styles.invitationsGrid}>
                {receivedInvitations.map((inv) => (
                  <div key={inv.id} className={styles.invitationCard}>
                    <div className={styles.invitationAvatar}>
                      {inv.firstName?.charAt(0)}
                      {inv.lastName?.charAt(0)}
                    </div>
                    <div className={styles.invitationInfo}>
                      <h4>
                        {inv.firstName} {inv.lastName}
                      </h4>
                      <p>{inv.email}</p>
                    </div>
                    <div className={styles.invitationActions}>
                      <button
                        className={styles.acceptBtn}
                        onClick={() => acceptInvitation(inv.id)}
                        disabled={
                          actionLoading.type === "acceptInvitation" &&
                          actionLoading.id === inv.id
                        }
                      >
                        {actionLoading.type === "acceptInvitation" &&
                        actionLoading.id === inv.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          "Accepter"
                        )}
                      </button>
                      <button
                        className={styles.rejectBtn}
                        onClick={() => deleteReceivedInvitation(inv.id)}
                        disabled={
                          actionLoading.type === "deleteReceived" &&
                          actionLoading.id === inv.id
                        }
                      >
                        {actionLoading.type === "deleteReceived" &&
                        actionLoading.id === inv.id ? (
                          <span className={styles.spinner}></span>
                        ) : (
                          "Refuser"
                        )}
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>

      {/* User Modal */}
      {isModalOpen && selectedUser && (
        <div className={styles.modalOverlay} onClick={handleCloseModal}>
          <div
            className={styles.modalContent}
            onClick={(e) => e.stopPropagation()}
          >
            <button className={styles.modalClose} onClick={handleCloseModal}>
              ✕
            </button>

            <div className={styles.modalHeader}>
              <div className={styles.modalAvatar}>
                {selectedUser.firstName?.charAt(0)}
                {selectedUser.lastName?.charAt(0)}
              </div>
              <h2 className={styles.modalTitle}>
                {selectedUser.firstName} {selectedUser.lastName}
              </h2>
            </div>

            <div className={styles.modalBody}>
              <div className={styles.modalInfo}>
                <div className={styles.infoRow}>
                  <span className={styles.infoLabel}>📧 Email:</span>
                  <span className={styles.infoValue}>{selectedUser.email}</span>
                </div>

                {selectedUser.availabilityStart && (
                  <div className={styles.infoRow}>
                    <span className={styles.infoLabel}>📅 Disponible du:</span>
                    <span className={styles.infoValue}>
                      {new Date(
                        selectedUser.availabilityStart,
                      ).toLocaleDateString()}
                    </span>
                  </div>
                )}

                {selectedUser.availabilityEnd && (
                  <div className={styles.infoRow}>
                    <span className={styles.infoLabel}>
                      📅 Disponible jusqu'au:
                    </span>
                    <span className={styles.infoValue}>
                      {new Date(
                        selectedUser.availabilityEnd,
                      ).toLocaleDateString()}
                    </span>
                  </div>
                )}
              </div>

              <div className={styles.modalActions}>
                <button
                  className={styles.primaryButton}
                  onClick={(e) => {
                    e.stopPropagation();
                    sendInvitation(selectedUser.id);
                  }}
                  disabled={
                    actionLoading.type === "sendInvitation" &&
                    actionLoading.id === selectedUser.id
                  }
                >
                  {actionLoading.type === "sendInvitation" &&
                  actionLoading.id === selectedUser.id ? (
                    <>
                      <span className={styles.spinner}></span>
                      Envoi...
                    </>
                  ) : (
                    "➕ Ajouter comme ami"
                  )}
                </button>
                <button
                  className={styles.secondaryButton}
                  onClick={handleCloseModal}
                >
                  Fermer
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default Profil;
