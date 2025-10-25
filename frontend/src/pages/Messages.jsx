import React, { useState, useEffect, useRef } from 'react';
import { Send, Search, MoreVertical, Phone, Video, Paperclip, Smile } from 'lucide-react';
import '../style/Messages.module.css';

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
      lastMessage: 'Hey! Comment va le projet?',
      time: '10:30',
      unread: 3,
      online: true,
      messages: [
        { id: 1, sender: 'them', text: 'Salut! Tu as vu les dernières maquettes?', time: '09:15' },
        { id: 2, sender: 'me', text: 'Oui je viens de les checker, elles sont top!', time: '09:20' },
        { id: 3, sender: 'them', text: 'Super! On peut en discuter cet aprem?', time: '09:25' },
        { id: 4, sender: 'me', text: 'Parfait, 15h ça te va?', time: '09:30' },
        { id: 5, sender: 'them', text: 'Hey! Comment va le projet?', time: '10:30' }
      ]
    },
    {
      id: 2,
      name: 'Thomas Dubois',
      avatar: 'https://i.pravatar.cc/150?img=3',
      lastMessage: 'La réunion est confirmée pour demain',
      time: '09:45',
      unread: 0,
      online: false,
      messages: [
        { id: 1, sender: 'them', text: 'La réunion est confirmée pour demain', time: '09:45' },
        { id: 2, sender: 'me', text: 'Ok merci pour la confirmation!', time: '09:50' }
      ]
    },
    {
      id: 3,
      name: 'Marie Lambert',
      avatar: 'https://i.pravatar.cc/150?img=5',
      lastMessage: 'J\'ai envoyé les documents',
      time: 'Hier',
      unread: 1,
      online: true,
      messages: [
        { id: 1, sender: 'them', text: 'J\'ai envoyé les documents par email', time: 'Hier 16:30' },
        { id: 2, sender: 'me', text: 'Reçu, je regarde ça!', time: 'Hier 16:35' },
        { id: 3, sender: 'them', text: 'Tu as pu les consulter?', time: '08:00' }
      ]
    },
    {
      id: 4,
      name: 'Alex Chen',
      avatar: 'https://i.pravatar.cc/150?img=8',
      lastMessage: 'On se voit au déjeuner?',
      time: 'Hier',
      unread: 0,
      online: false,
      messages: [
        { id: 1, sender: 'them', text: 'On se voit au déjeuner?', time: 'Hier 11:30' },
        { id: 2, sender: 'me', text: 'Désolé j\'ai déjà prévu quelque chose', time: 'Hier 11:45' }
      ]
    }
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

    setConversations(prev => prev.map(conv => {
      if (conv.id === selectedChat.id) {
        return {
          ...conv,
          messages: [...conv.messages, newMessage],
          lastMessage: messageInput,
          time: 'Maintenant'
        };
      }
      return conv;
    }));

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
    if (conversations.length > 0 && !selectedChat) {
      setSelectedChat(conversations[0]);
    }
  }, []);

  return (
    <div className="messages-container">
      {/* Sidebar - Liste des conversations */}
      <div className="sidebar">
        {/* Header */}
        <div className="sidebar-header">
          <h1 className="title">Messages</h1>
          <div className="search-container">
            <Search className="search-icon" size={20} />
            <input
              type="text"
              placeholder="Rechercher une conversation..."
              className="search-input"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
            />
          </div>
        </div>

        {/* Liste des conversations */}
        <div className="conversations-list">
          {filteredConversations.map(conv => (
            <div
              key={conv.id}
              onClick={() => setSelectedChat(conv)}
              className={`conversation-item ${selectedChat?.id === conv.id ? 'active' : ''}`}
            >
              <div className="conversation-content">
                <div className="avatar-container">
                  <img src={conv.avatar} alt={conv.name} className="avatar" />
                  {conv.online && <div className="online-indicator"></div>}
                </div>
                <div className="conversation-info">
                  <div className="conversation-header">
                    <h3 className="conversation-name">{conv.name}</h3>
                    <span className="conversation-time">{conv.time}</span>
                  </div>
                  <p className="last-message">{conv.lastMessage}</p>
                </div>
                {conv.unread > 0 && (
                  <div className="unread-badge">{conv.unread}</div>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* Zone de chat */}
      {selectedChat ? (
        <div className="chat-container">
          {/* Header du chat */}
          <div className="chat-header">
            <div className="chat-header-left">
              <div className="avatar-container">
                <img src={selectedChat.avatar} alt={selectedChat.name} className="chat-avatar" />
                {selectedChat.online && <div className="chat-online-indicator"></div>}
              </div>
              <div className="chat-user-info">
                <h2 className="chat-user-name">{selectedChat.name}</h2>
                <p className="chat-user-status">
                  {selectedChat.online ? 'En ligne' : 'Hors ligne'}
                </p>
              </div>
            </div>
            <div className="chat-actions">
              <button className="action-button">
                <Phone size={20} />
              </button>
              <button className="action-button">
                <Video size={20} />
              </button>
              <button className="action-button">
                <MoreVertical size={20} />
              </button>
            </div>
          </div>

          {/* Messages */}
          <div className="messages-area">
            {selectedChat.messages.map((msg) => (
              <div key={msg.id} className={`message-wrapper ${msg.sender === 'me' ? 'me' : 'them'}`}>
                <div className={`message-bubble ${msg.sender === 'me' ? 'me' : 'them'}`}>
                  <p className="message-text">{msg.text}</p>
                  <span className="message-time">{msg.time}</span>
                </div>
              </div>
            ))}
            <div ref={messagesEndRef} />
          </div>

          {/* Input de message */}
          <div className="input-container">
            <div className="input-wrapper">
              <button type="button" className="input-action-button">
                <Paperclip size={20} />
              </button>
              <button type="button" className="input-action-button">
                <Smile size={20} />
              </button>
              <input
                type="text"
                placeholder="Écrivez votre message..."
                className="message-input"
                value={messageInput}
                onChange={(e) => setMessageInput(e.target.value)}
                onKeyPress={handleKeyPress}
              />
              <button onClick={handleSendMessage} className="send-button">
                <Send size={20} />
              </button>
            </div>
          </div>
        </div>
      ) : (
        <div className="empty-state">
          <div className="empty-state-content">
            <div className="empty-state-icon">
              <Send size={48} />
            </div>
            <h3 className="empty-state-title">Sélectionnez une conversation</h3>
            <p className="empty-state-text">Choisissez une conversation pour commencer à discuter</p>
          </div>
        </div>
      )}
    </div>
  );
};

export default Messages;