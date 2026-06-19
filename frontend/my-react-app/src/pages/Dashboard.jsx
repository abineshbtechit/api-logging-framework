import { useEffect, useState } from "react";
import api from "../api/axios";

function Dashboard() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    api.get("/me")
      .then((res) => {
        setUser(res.data.user);
      })
      .catch(() => {
        setError("Unable to load dashboard data.");
      })
      .finally(() => {
        setLoading(false);
      });
  }, []);

  if (loading) {
    return <div>Loading dashboard...</div>;
  }

  if (error) {
    return <div>{error}</div>;
  }

  return (
    <div>
      <h2>Dashboard</h2>

      {user?.role === "professor" && (
        <h3>Professor Dashboard</h3>
      )}

      {user?.role === "student" && (
        <h3>Student Dashboard</h3>
      )}

      {!user?.role && (
        <h3>Dashboard data is available, but no role-specific view matched.</h3>
      )}
    </div>
  );
}

export default Dashboard;