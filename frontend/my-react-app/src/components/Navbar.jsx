import { Link, useNavigate } from "react-router-dom";
import { useEffect, useState } from "react";
import api from "../api/axios";

function Navbar() {
  const navigate = useNavigate();
  const [user, setUser] = useState(null);

  const logout = () => {
    localStorage.removeItem("token");
    navigate("/");
  };
  useEffect(() => {
    api.get("/me").then((res) => setUser(res.data.user));
  }, []);

  return (
    <nav
      style={{
        display: "flex",
        gap: "20px",
        padding: "15px",
        backgroundColor: "#f4f4f4",
      }}
    >
      <Link to="/dashboard">Dashboard</Link>

      <Link to="/notes">Notes</Link>

      {user?.role === "professor" && <Link to="/create-note">Create Note</Link>}

      <button onClick={logout}>Logout</button>
    </nav>
  );
}

export default Navbar;
