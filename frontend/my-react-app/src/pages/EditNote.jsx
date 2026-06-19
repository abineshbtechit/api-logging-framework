import React, { useEffect, useState } from "react";
import api from "../api/axios";
import { useNavigate, useParams } from "react-router-dom";

export default function EditNote() {
  const { id } = useParams();
  const navigate = useNavigate();

  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");

  useEffect(() => {
    api.get(`/notes/${id}`)
      .then((res) => {
        setTitle(res.data.title);
        setContent(res.data.content);
      })
      .catch((err) => console.log(err));
  }, [id]);

  const handleUpdate = async (e) => {
    e.preventDefault();

    try {
      await api.put(`/notes_u/${id}`, {
        title,
        content,
      });

      alert("Note Updated Successfully");
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
      <h2>Edit Note</h2>

      <form onSubmit={handleUpdate}>
        <input
          type="text"
          value={title}
          onChange={(e) => setTitle(e.target.value)}
          placeholder="Enter Title"
        />

        <br /><br />

        <textarea
          rows="5"
          cols="40"
          value={content}
          onChange={(e) => setContent(e.target.value)}
          placeholder="Enter Content"
        />

        <br /><br />

        <button type="submit">Update Note</button>
      </form>
    </div>
  );
}