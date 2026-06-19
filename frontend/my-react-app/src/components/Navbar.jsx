import { Link, useNavigate } from "react-router-dom";

function Navbar() {
  const navigate = useNavigate();

  const logout = () => {
    localStorage.removeItem("token");
    navigate("/");
  };

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

      <Link to="/create-note">Create Note</Link>

      <button onClick={logout}>
        Logout
      </button>
    </nav>
  );
}

export default Navbar;