# API Logging Framework (v1)

A Laravel + React application with centralized API request/response logging, log filtering, and automated log archiving.

## Stack
- **Backend:** Laravel (PHP), Laravel Sanctum
- **Frontend:** React (Vite, React Router, Axios)
- **Databases:** PostgreSQL — `pgsql` (app data) + `log_pgsql` (API logs & archives)

---

## What's Implemented

### 1. Automatic API Logging (Zero Response-Time Impact)
- `ApiLoggerMiddleware` (global) uses Laravel's **terminable middleware** (`handle()` + `terminate()`):
  1. `handle()` starts a timer, lets the controller run, builds the log payload, and attaches it to the request.
  2. **The response is sent to the client immediately.**
  3. `terminate()` runs **after** the response is sent, writing the log to `api_logs` (`log_pgsql`) so logging never slows down the customer's response time.
- **Queueing (`StoreApiLogJob` + `jobs` table):** A queued job `App\Jobs\StoreApiLogJob` (`ShouldQueue`) and `jobs` table migration are also included for async DB writes via `php artisan queue:work` (currently `terminate()` calls `ApiLog::create($logData)` directly; swap to `StoreApiLogJob::dispatch($logData)` with `QUEUE_CONNECTION=database` to queue inserts).
- Captures: `user_id`, `user_role`, `method`, `endpoint`, `request_headers`, `request_body`, `response_body`, `status_code`, `response_time`, `ip_address`, `user_agent`.

### 2. Log Query API
- `GET /api/logs` (`ApiLogController@index`)
- Supports query filters: `method`, `status`, `from`, `to`.

### 3. Log Archiving
- Artisan command: `php artisan logs:archive --days=90 --chunk=1000` (`ArchiveApiLogs`)
- Exports old `api_logs` to CSV (`api-log-archives/`), records metadata in `api_log_archives`, and deletes archived rows.
- Scheduled in `backend/app/Console/Kernel.php`.

### 4. Auth & Notes CRUD
- **Auth (`AuthController` + Sanctum):** `POST /api/login`, `POST /api/logout`, `GET /api/me` (with `LoginAlertMail`).
- **Notes (`NoteController`):** `GET /api/notes`, `GET /api/notes/{id}`, `POST /api/note_c`, `PUT /api/notes_u/{id}`, `DELETE /api/notes_d/{id}`.
- **Test Endpoints (`TestController`):** `GET /api/getting`, `POST /api/posting`, `PUT /api/putting/{id}`, `DELETE /api/deleting/{id}`.

### 5. Frontend (`frontend/my-react-app`)
- Pages: `Login` (`/`), `Dashboard` (`/dashboard`), `Notes` (`/notes`), `CreateNote` (`/create-note`), `EditNote` (`/edit-note/:id`).
- `Navbar` component + Axios client (`src/api/axios.js`).

### 6. Performance Script
- `performance_test.py` — sends 100 requests to benchmark API response times.

---

## Quick Start

**Backend**
```bash
cd backend
composer install
cp .env.example .env   # configure pgsql & log_pgsql
php artisan key:generate
php artisan migrate
php artisan serve
```

**Frontend**
```bash
cd frontend/my-react-app
npm install
npm run dev
```
