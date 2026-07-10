# API Logging Framework

> A production-grade, full-stack Laravel platform for centralized API observability —
> built to capture every request and response, monitor performance, and give teams
> complete visibility into their API traffic without slowing it down.

&nbsp;

![Laravel](https://img.shields.io/badge/Laravel-Backend%20API-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-Axios%20%2B%20Bootstrap-61DAFB?style=for-the-badge&logo=react&logoColor=black)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-Dual%20Database-4169E1?style=for-the-badge&logo=postgresql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Auth-Laravel%20Sanctum-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![AWS](https://img.shields.io/badge/AWS-EC2%20%2B%20Nginx-FF9900?style=for-the-badge&logo=amazonaws&logoColor=white)
![Nginx](https://img.shields.io/badge/Nginx-Reverse%20Proxy-269539?style=for-the-badge&logo=nginx&logoColor=white)

&nbsp;

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [System Architecture](#system-architecture)
- [Modules](#modules)
- [Local Setup](#local-setup)
- [Environment Variables](#environment-variables)
- [Project Structure](#project-structure)
- [Key Engineering Decisions](#key-engineering-decisions)
- [Feature Checklist](#feature-checklist)

---

&nbsp;

## Overview

> *What problem does this solve, and why does it matter?*

Most APIs are invisible until something breaks. Without a structured logging layer, developers fall back on scattered `var_dump()` calls, generic web-server logs, or expensive third-party APM tools just to answer basic questions like *"who called this endpoint, what did they send, and why did it fail?"*

**API Logging Framework** is a Laravel-based backend that captures, stores, filters, and manages logs for **every single API request and response** — success or failure — with zero impact on client-facing response time. It ships alongside a complete authentication, role-based access, and CRUD system, and is deployed on real AWS infrastructure.

This is not a bolt-on logging library. It is a **fully engineered observability layer**, built and iterated through three real implementation attempts before arriving at its final, production architecture.

&nbsp;

**Two roles. One platform.**

| Role | Capabilities |
| :--- | :--- |
| **Professor** | Create, update, and delete notes; full read access; managed via Student CRUD |
| **Student** | Read-only access to notes; authenticated via Sanctum token |

&nbsp;

---

&nbsp;

## Tech Stack

> *Every technology in this stack was chosen deliberately — here's the full picture.*

| Layer | Technology | Purpose |
| :--- | :--- | :--- |
| **Backend** | Laravel, PHP | REST APIs, routing, middleware pipeline, Eloquent ORM |
| **Frontend** | React, Axios, Bootstrap | SPA client, HTTP communication, UI |
| **Authentication** | Laravel Sanctum | Lightweight, revocable token-based API auth |
| **Primary Database** | PostgreSQL (`pgsql`) | Students, notes, personal access tokens |
| **Logging Database** | PostgreSQL (`log_pgsql`) | Isolated storage for `api_logs` — physically separate connection |
| **Cloud Platform** | AWS EC2 | Production hosting |
| **Server** | Ubuntu Linux, Nginx | OS and reverse proxy / static file serving |
| **Tooling** | Git, GitHub, Postman, Composer, VS Code | Version control, API testing, dependency management |

&nbsp;

---

&nbsp;

## System Architecture

> *How a request flows from the client down through middleware, the controller, and both databases.*

```
Client (Browser)
      ↓
React Frontend → Axios (HTTP client)
      ↓
Laravel
      ↓
Global Middleware  →  ApiLoggerMiddleware::handle() starts a timer
      ↓
Sanctum Authentication
      ↓
Controller (business logic / validation / CRUD)
      ↓
Main Database (PostgreSQL) — students | notes | personal_access_tokens
      ↓
Response sent back to client immediately
      ↓
Terminate Middleware  →  ApiLoggerMiddleware::terminate() runs AFTER the response is sent
      ↓
Log Database (PostgreSQL, log_pgsql connection) — api_logs
```

**Why this matters:** because `ApiLoggerMiddleware` is registered globally rather than inside a route or group, it runs on *every* request — including ones that never reach the controller (401, 403, 404). The log write itself happens in `terminate()`, after the client already has its response, so logging adds zero perceived latency.

&nbsp;

---

&nbsp;

## Modules

> *A breakdown of every feature built into the platform — what it does and how it works.*

&nbsp;

### 1. Authentication

Token-based login via Laravel Sanctum. Login, logout, and a `/me` endpoint for the current authenticated user. Passwords are Bcrypt-hashed, and a login alert email is dispatched on every successful sign-in.

&nbsp;

---

&nbsp;

### 2. Role-Based Access Control

Two roles — **Professor** and **Student** — enforced through a dedicated `RoleMiddleware` on top of Sanctum's `auth:sanctum` guard. Professors can create, update, and delete notes; Students are restricted to read-only access. Role checks happen both at the route level and, where relevant, inside controller logic as a second line of defense.

&nbsp;

---

&nbsp;

### 3. Student CRUD

Full CRUD on student/professor records (`GET`, `POST`, `PUT`, `DELETE`). Every write is validated through Laravel's Form Request validation, and the `Student` model declares an explicit `$fillable` allow-list to prevent mass-assignment vulnerabilities.

&nbsp;

---

&nbsp;

### 4. Notes Management

A classic one-to-many Eloquent relationship: a `Student` `hasMany()` `Notes`, and each `Note` `belongsTo()` a `Student` via `user_id`. Write access (create/update/delete) is restricted to Professors; Students can only view.

&nbsp;

---

&nbsp;

### 5. API Logging Middleware — the core of the system

Every request and response passing through the application is captured: authenticated user and role, HTTP method, endpoint, headers, request body, response body, IP address, user-agent, status code, and precise response time.

**The key engineering detail:** the logger is registered as **global middleware** in `Kernel.php`, not inside the `api` middleware group. Middleware groups only execute once the router has matched a route — so requests that fail earlier (401 unauthorized, 403 forbidden, 404 not found) never reached that layer. Moving the logger to global middleware guarantees every outcome — 200, 201, 400, 401, 403, 404, 422, 500 — is captured, without exception.

The log write itself happens inside `terminate()`, which Laravel calls only *after* the response has already been sent to the client — so the database write for the log entry never adds to what the user experiences.

&nbsp;

---

&nbsp;

### 6. Log Filter API

Query the `api_logs` table by any combination of filters, returned as paginated results:

| Filter | Query Parameter | Description |
| :--- | :--- | :--- |
| Method | `method` | Filter by HTTP method — `GET`, `POST`, `PUT`, `DELETE` |
| Status Code | `status_code` | Filter by exact HTTP status, e.g. `404`, `500` |
| Date Range | `from` / `to` | Filter logs created within a timestamp range |
| Endpoint | `endpoint` | Partial match against the route/URI |
| User | `user_id` | Filter logs generated by a specific authenticated user |

&nbsp;

---

&nbsp;

### 7. Dual-Database Architecture

Two independent PostgreSQL databases, connected through two separate Laravel connection configs: the default `pgsql` connection for core application data, and a dedicated `log_pgsql` connection used exclusively by the `ApiLog` model. This keeps high-volume, non-critical log writes from ever competing with business data for database resources.

&nbsp;

---

&nbsp;

### 8. AWS Deployment

The complete stack — Laravel backend, React frontend, and both PostgreSQL databases — is deployed on a single Ubuntu EC2 instance, fronted by Nginx as both a static file server (React build) and a reverse proxy (API requests to PHP-FPM). Backed by AWS Backup (vault, plan, recovery points) for disaster recovery.

&nbsp;

---

&nbsp;

## Local Setup

> *Get the full stack running locally.*

### Prerequisites

| Dependency | Minimum Version | Notes |
| :--- | :--- | :--- |
| **PHP** | `8.1+` | Required to run Laravel |
| **Composer** | `2.0+` | PHP dependency manager |
| **Node.js** | `18+` | Required for the React frontend |
| **PostgreSQL** | `13+` | Two databases: main app + logging |

&nbsp;

### 1. Clone the Repository

```bash
git clone <your-repo-url>
cd api-logging-framework
```

&nbsp;

### 2. Backend Setup

```bash
cd backend

# Install dependencies
composer install

# Configure environment variables
cp .env.example .env              # Fill in all values (see Environment Variables section)
php artisan key:generate

# Run migrations on both connections
php artisan migrate
php artisan migrate --database=log_pgsql

# Link storage
php artisan storage:link
```

&nbsp;

**Start the backend:**

```bash
php artisan serve
```

&nbsp;

### 3. Frontend Setup

```bash
cd frontend

npm install
npm run dev
```

The app will be available at `http://localhost:5173` by default.

&nbsp;

---

&nbsp;

## Environment Variables

> *All secrets and configuration values live in a single `.env` file — never committed to source control.*

Create a `.env` file in the `/backend` directory. Use the reference below:

| Variable | Description |
| :--- | :--- |
| `DB_CONNECTION` | Main database driver, e.g. `pgsql` |
| `DB_HOST` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Main application database credentials |
| `LOG_DB_HOST` / `LOG_DB_DATABASE` / `LOG_DB_USERNAME` / `LOG_DB_PASSWORD` | Logging database credentials (`log_pgsql` connection) |
| `SANCTUM_STATEFUL_DOMAINS` | Frontend domain(s) allowed to authenticate via Sanctum |
| `MAIL_MAILER` / `MAIL_HOST` / `MAIL_USERNAME` / `MAIL_PASSWORD` | Mail configuration for login alert emails |

**Example `.env`:**

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=main_app
DB_USERNAME=app_user
DB_PASSWORD=your_password

LOG_DB_HOST=127.0.0.1
LOG_DB_DATABASE=api_logs_db
LOG_DB_USERNAME=app_user
LOG_DB_PASSWORD=your_password

SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

&nbsp;

> **Note:** The `.env` file contains sensitive credentials. It is already listed in `.gitignore` — never commit it to version control.

&nbsp;

---

&nbsp;

## Project Structure

```
api-logging-framework/
│
├── backend/
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/          # AuthController, StudentController, NoteController, LogController
│   │   │   ├── Middleware/
│   │   │   │   ├── ApiLoggerMiddleware.php   # Global middleware — handle() + terminate()
│   │   │   │   └── RoleMiddleware.php        # Route-level role enforcement
│   │   │   └── Kernel.php            # Global / group / route middleware registration
│   │   └── Models/
│   │       ├── Student.php           # hasMany(Note)
│   │       ├── Note.php              # belongsTo(Student)
│   │       └── ApiLog.php            # Uses the log_pgsql connection
│   ├── config/
│   │   └── database.php              # pgsql + log_pgsql connection definitions
│   ├── database/
│   │   └── migrations/               # students, notes, api_logs schemas
│   ├── routes/
│   │   └── api.php
│   ├── .env
│   ├── composer.json
│   └── artisan
│
└── frontend/
    ├── src/
    │   ├── components/
    │   ├── pages/
    │   │   ├── LoginPage.jsx
    │   │   ├── NotesPage.jsx
    │   │   ├── StudentsPage.jsx
    │   │   └── LogsPage.jsx           # Filterable API log viewer
    │   ├── services/
    │   │   └── api.js                # Axios instance with Sanctum token attached
    │   ├── App.jsx
    │   └── main.jsx
    ├── package.json
    └── vite.config.js
```

&nbsp;

---

&nbsp;

## Key Engineering Decisions

> *The reasoning behind the non-obvious choices — what drove each architectural decision and what problem it solves.*

&nbsp;

### Why global middleware instead of a middleware group?

Middleware groups (like `api`) only execute once Laravel's router has successfully matched a route. That meant requests failing authentication (401), authorization (403), or hitting a non-existent route (404) never reached the logger — entire categories of failure were invisible. Registering `ApiLoggerMiddleware` in `Kernel.php`'s global `$middleware` array fixed this: it now runs unconditionally on every request, before routing, before authentication, guaranteeing complete visibility into both successes and failures.

&nbsp;

### Why `terminate()` instead of logging inline in `handle()`?

Logging inline inside `handle()` meant the client waited for the `ApiLog::create()` database write to finish before receiving a response — under load, this directly slowed down every API call. `terminate()` runs only *after* the response has already been sent to the client, so the log write happens entirely behind the scenes with zero perceived latency.

&nbsp;

### Why not use a queue (`ShouldQueue`) for logging?

A queue-based approach was implemented and evaluated first — it removes the log write from the request cycle, but requires a permanently running queue worker, a `jobs` table, and added operational complexity disproportionate to the problem. Terminable middleware achieves the same "the client never waits" benefit within the same request lifecycle, with no extra infrastructure to deploy or monitor.

&nbsp;

### Why two separate PostgreSQL databases?

Log writes happen on every single API call — at scale, that's a very high write volume. Isolating `api_logs` in its own `log_pgsql` connection ensures logging traffic never causes lock contention or performance degradation on tables like `students` and `notes` that serve real user-facing requests.

&nbsp;

---

&nbsp;

## Feature Checklist

> *Every capability shipped in the current version — tracked for transparency.*

| Feature | Status |
| :--- | :---: |
| Sanctum Token Authentication | ✅ |
| Role-Based Access Control — Professor & Student | ✅ |
| Login Alert Emails | ✅ |
| Student CRUD with Validation & Mass-Assignment Protection | ✅ |
| Notes Management (One-to-Many Relationship) | ✅ |
| Global Middleware API Request/Response Logging | ✅ |
| Terminate Middleware — Zero-Latency Log Writes | ✅ |
| Dual PostgreSQL Database Architecture | ✅ |
| Log Filter API (Method, Status, Date Range, Endpoint, User) | ✅ |
| AWS EC2 + Nginx Production Deployment | ✅ |
| AWS Backup (Vault, Plan, Recovery Points) | ✅ |
| Postman-Verified Error Handling (401 / 403 / 404 / 422 / 500) | ✅ |

&nbsp;

---

&nbsp;

---
