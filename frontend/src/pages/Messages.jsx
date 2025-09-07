const Messages = () => {
      const token = localStorage.getItem("token");
      console.log(token);
      <h2>token</h2>
  return (
    <div>
      <p>12</p>
      <h2>Messages</h2>
      <p>token : {token}</p>
    </div>
  );

};

export default Messages;