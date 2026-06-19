import React, { useEffect, useState } from "react";
import api from "../api/axios";
import { Link } from "react-router-dom";

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

  const handleDelete = async (id) => {
  const confirmDelete = window.confirm("Are you sure you want to delete this note?");

  if (!confirmDelete) return;

  try {
    await api.delete(`/notes_d/${id}`);

    alert("Note deleted successfully");

    setNotes(notes.filter((note) => note.id !== id));
  } catch (error) {
    console.log(error);
    if (error.response && error.response.data && error.response.data.message) {
      alert(`Failed to delete note: ${error.response.data.message}`);
    } else {
      alert("Failed to delete note");
    }
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
            <Link to={`/edit-note/${note.id}`}>Edit</Link>
            <br />
            <button className="btn-delete" onClick={() => handleDelete(note.id)}>Delete</button>
          </div>
        ))
      )}
    </div>
  );
}
