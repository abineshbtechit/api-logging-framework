import React, { useState } from "react";
import api from "../api/axios";
import { useNavigate } from "react-router-dom";

export default function CreateNote() {
  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");

  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();

    try {
      await api.post("/note_c", {
        title,
        content,
      });

      alert("Note Created Successfully");

      navigate("/notes");
    } catch (error) {
      console.log(error);

      if (error.response) {
        alert(error.response.data.message);
      }
    }
  };

  return (
    <div>
      <h2>Create Note</h2>

      <form onSubmit={handleSubmit}>
        <div>
          <label>Title</label>
          <br />

          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="Enter Title"
          />
        </div>

        <br />

        <div>
          <label>Content</label>
          <br />

          <textarea
            rows="5"
            cols="40"
            value={content}
            onChange={(e) => setContent(e.target.value)}
            placeholder="Enter Content"
          />
        </div>

        <br />

        <button type="submit">Create Note</button>
      </form>
    </div>
  );
}
