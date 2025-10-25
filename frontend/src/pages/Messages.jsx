import React, { useState, useEffect, useRef } from 'react';
import styles from '../style/Message.module.css';

const Messages = () => {
  const [selectedChat, setSelectedChat] = useState(null);
  const [searchTerm, setSearchTerm] = useState('');
  const [messageInput, setMessageInput] = useState('');
  const messagesEndRef = useRef(null);

  const [conversations, setConversations] = useState([
    {
      id: 1,
      name: 'Sarah Martin',
      avatar: 'https://i.pravatar.cc/150?img=1',
      lastMessage: 'À tout à l’heure!',
      time: '10:30',
      unread: 3,
      online: true,
      messages: [
        { id: 1, sender: 'them', text: 'Salut! Tu as vu les dernières maquettes?', time: '09:15' },
        { id: 2, sender: 'me', text: 'Oui, elles sont top!', time: '09:20' },
        { id: 3, sender: 'them', text: 'On peut en discuter cet aprem?', time: '09:25' },
        { id: 4, sender: 'me', text: 'Parfait, 15h ça te va?', time: '09:30' },
        { id: 5, sender: 'them', text: 'À tout à l’heure!', time: '10:30' },
      ],
    },
    {
      id: 2,
      name: 'Thomas Dubois',
      avatar: 'https://i.pravatar.cc/150?img=3',
      lastMessage: 'Merci pour la confirmation!',
      time: '09:50',
      unread: 0,
      online: false,
      messages: [
        { id: 1, sender: 'them', text: 'La réunion est confirmée pour demain', time: '09:45' },
        { id: 2, sender: 'me', text: 'Merci pour la confirmation!', time: '09:50' },
        { id: 3, sender: 'them', text: 'N’oublie pas d’apporter le rapport', time: '09:52' },
      ],
    },
    {
      id: 3,
      name: 'Marie Lambert',
      avatar: 'https://i.pravatar.cc/150?img=5',
      lastMessage: 'Super, merci!',
      time: 'Hier',
      unread: 1,
      online: true,
      messages: [
        { id: 1, sender: 'them', text: 'J’ai envoyé les documents par email', time: 'Hier 16:30' },
        { id: 2, sender: 'me', text: 'Reçu, je regarde ça!', time: 'Hier 16:35' },
        { id: 3, sender: 'them', text: 'Tu as pu les consulter?', time: '08:00' },
        { id: 4, sender: 'me', text: 'Oui, super boulot!', time: '08:15' },
        { id: 5, sender: 'them', text: 'Super, merci!', time: '08:20' },
      ],
    },
  ]);

  const filteredConversations = conversations.filter(conv =>
    conv.name.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const handleSendMessage = () => {
    if (!messageInput.trim() || !selectedChat) return;

    const newMessage = {
      id: Date.now(),
      sender: 'me',
      text: messageInput,
      time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
    };

    setConversations(prev =>
      prev.map(conv =>
        conv.id === selectedChat.id
          ? {
              ...conv,
              messages: [...conv.messages, newMessage],
              lastMessage: messageInput,
              time: 'Maintenant',
            }
          : conv
      )
    );

    setMessageInput('');
  };

  const handleKeyPress = (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSendMessage();
    }
  };

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [selectedChat?.messages]);

  useEffect(() => {
    if (!selectedChat && conversations.length > 0) {
      setSelectedChat(conversations[0]);
    }
  }, []);

  return (
    <div className={styles['msg-container']}>
      {/* Sidebar */}
      <div className={styles['msg-sidebar']}>
        <div className={styles['msg-sidebar-header']}>
          <h1 className={styles['msg-title']}>Messages</h1>
          <div className={styles['msg-search-container']}>
            🔍
            <input
              type="text"
              placeholder="Rechercher..."
              className={styles['msg-search-input']}
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        <div className={styles['msg-conversations-list']}>
          {filteredConversations.map(conv => (
            <div
              key={conv.id}
              onClick={() => setSelectedChat(conv)}
              className={`${styles['msg-conversation-item']} ${
                selectedChat?.id === conv.id ? styles['msg-active'] : ''
              }`}
            >
              <div className={styles['msg-conversation-content']}>
                <div className={styles['msg-avatar-container']}>
                  <img src={conv.avatar} alt={conv.name} className={styles['msg-avatar']} />
                  {conv.online && <div className={styles['msg-online-indicator']}></div>}
                </div>
                <div className={styles['msg-conversation-info']}>
                  <div className={styles['msg-conversation-header']}>
                    <h3 className={styles['msg-conversation-name']}>{conv.name}</h3>
                    <span className={styles['msg-conversation-time']}>{conv.time}</span>
                  </div>
                  <p className={styles['msg-last-message']}>{conv.lastMessage}</p>
                </div>
                {conv.unread > 0 && <div className={styles['msg-unread-badge']}>{conv.unread}</div>}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Chat */}
      {selectedChat ? (
        <div className={styles['msg-chat-container']}>
          <div className={styles['msg-chat-header']}>
            <div className={styles['msg-chat-header-left']}>
              <div className={styles['msg-avatar-container']} style={{ position: 'relative' }}>
                <img src={selectedChat.avatar} alt={selectedChat.name} className={styles['msg-chat-avatar']} />
                {selectedChat.online && <div className={styles['msg-chat-online-indicator']}></div>}
              </div>
              <div className={styles['msg-chat-user-info']}>
                <h2 className={styles['msg-chat-user-name']}>{selectedChat.name}</h2>
                <p className={styles['msg-chat-user-status']}>{selectedChat.online ? 'En ligne' : 'Hors ligne'}</p>
              </div>
            </div>
            <div className={styles['msg-chat-actions']}>
              <button className={styles['msg-action-button']}>📞</button>
              <button className={styles['msg-action-button']}>🎥</button>
            </div>
          </div>

          <div className={styles['msg-messages-area']}>
            {selectedChat.messages.map(msg => (
              <div key={msg.id} className={`${styles['msg-message-wrapper']} ${msg.sender}`}>
                <div className={`${styles['msg-message-bubble']} ${msg.sender}`}>
                  <p className={styles['msg-message-text']}>{msg.text}</p>
                  <span className={styles['msg-message-time']}>{msg.time}</span>
                </div>
              </div>
            ))}
            <div ref={messagesEndRef} />
          </div>

          <div className={styles['msg-input-container']}>
            <div className={styles['msg-input-wrapper']}>
              <button type="button" className={styles['msg-input-action-button']}>📎</button>
              <button type="button" className={styles['msg-input-action-button']}>😊</button>
              <input
                type="text"
                placeholder="Écrivez votre message..."
                className={styles['msg-message-input']}
                value={messageInput}
                onChange={(e) => setMessageInput(e.target.value)}
                onKeyPress={handleKeyPress}
              />
              <button onClick={handleSendMessage} className={styles['msg-send-button']}>📤</button>
            </div>
          </div>
        </div>
      ) : (
        <div className={styles['msg-empty-state']}>
          <div className={styles['msg-empty-state-content']}>
            <div className={styles['msg-empty-state-icon']}>📤</div>
            <h3 className={styles['msg-empty-state-title']}>Sélectionnez une conversation</h3>
            <p className={styles['msg-empty-state-text']}>Choisissez une conversation pour commencer à discuter</p>
          </div>
        </div>
      )}
    </div>
  );
};

export default Messages;
