import { BrowserRouter, Routes, Route } from "react-router-dom";

import Login from "./pages/Login";
import Dashboard from "./pages/Dashboard";
import Notes from "./pages/Notes";
import CreateNote from "./pages/CreateNote";
import EditNote from "./pages/EditNote";
import Navbar from "./components/Navbar";
import DeleteNote from "./pages/DeleteNote";

function App() {
  return (
    <BrowserRouter>
      <Navbar />


      <Routes>
        <Route path="/" element={<Login />} />
        <Route path="/dashboard" element={<Dashboard />} />
        <Route path="/notes" element={<Notes />} />
        <Route path="/create-note" element={<CreateNote />} />
        <Route path="/edit-note/:id" element={<EditNote />} />
         {/* <Route path="/delete-note/:id" element={<DeleteNote />} /> */}
      </Routes>


    </BrowserRouter>
  );
}

export default App;