import React, { useEffect, useState } from "react";
import api from "../api/axios";

export default function Notes() {
  const [notes, setNotes] = useState([]);

  useEffect(() => {
    fetchNotes();
  }, []);

  const fetchNotes = async () => {
    try {
      const response = await api.get("/notes");
      setNotes(response.data);
    } catch (error) {
      console.log(error);
    }
  };

  return (
    <div>
      <h2>All Notes</h2>

      {notes.length === 0 ? (
        <p>No Notes Found</p>
      ) : (
        notes.map((note) => (
          <div
            key={note.id}
            style={{
              border: "1px solid black",
              padding: "10px",
              margin: "10px",
            }}
          >
            <h3>{note.title}</h3>

            <p>{note.content}</p>

            <p>
              <strong>Author:</strong> {note.user ? note.user.name : "Unknown"}
            </p>
          </div>
        ))
      )}
    </div>
  );
}
