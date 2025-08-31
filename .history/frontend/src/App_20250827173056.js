import logo from './logo.svg';
import './App.css';
import { useEffect, useState } from "react";

function App() {
  const [users, setUsers] = useState([]);
useEffect(() => {
  fetch("http://localhost:8000/api/register", {
  method: "POST",
  headers: {
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    nom: "Doe",
    prenom: "John",
    courriel: "john.doe@mail.com",
    motDePasse: "monSuperMotDePasse"
  }),
})
  .then((res) => res.json())
  .then((data) => console.log("Réponse API:", data));

}, []);


  return (
    <div>
      <h1>Liste des utilisateurs</h1>
      <ul>
        {users.map((user) => (
          <li key={user.id}>{user.name}</li>
        ))}
      </ul>
    </div>
  );
}

export default App;