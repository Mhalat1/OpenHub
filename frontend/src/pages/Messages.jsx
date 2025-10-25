import React, { useEffect, useState } from 'react';
import "../style/Message.module.css";


const Messages = () => {
  const [token, setToken] = useState('');

  useEffect(() => {
    const storedToken = localStorage.getItem('token');
    if (storedToken) {
      setToken(storedToken);
    }
  }, []);

  return (
    <div className={styles.container}>
      <h1 className={styles.title}>Messages Page</h1>


      <div className={styles.tokenDisplay}>
        <strong>Token:</strong> <h4>{token}</h4>
      </div>
    </div>
  );
}
    

  

export default Messages;