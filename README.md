# TMS-B — Task Management System (Backend)

A RESTful API backend for the Task Management System, built with **Laravel 12** (PHP 8.2+). It powers the [tms-f](https://github.com/PaudelN/tms-f) frontend and provides all data management, authentication, Kanban board logic, and file-attachment capabilities.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.2+ |
| Database | MySQL |
| Authentication | Laravel Sanctum (token-based + 2FA support) |
| File storage | Laravel Storage (public disk) |
| API style | RESTful JSON API |

---

## Features Implemented

### Authentication
- Token-based authentication via Laravel Sanctum
- Two-factor authentication (2FA) support fields on users table

### Workspaces
- Full CRUD (create, read, update, delete)
- Kanban board view and drag-and-drop move/reorder
- Workspace membership (many-to-many with users)
- Workspace status enum
- Aggregated counts endpoint

### Projects
- Full CRUD, nested under workspaces
- Kanban board view and drag-and-drop move/reorder
- Project visibility and status enums
- Aggregated counts endpoint

### Pipelines
- Full CRUD, nested under projects
- Pipeline status enum
- Aggregated counts endpoint

### Pipeline Stages
- Full CRUD, nested under pipelines
- Stage reordering endpoint
- Stage status enum
- Aggregated counts endpoint

### Tasks
- Full CRUD, nested under pipeline stages
- Kanban move and reorder across stages
- Task priority enum
- "My tasks" and "All tasks" list endpoints
- Task assignment to users

### Media / File Attachments
- Polymorphic media library — any model (Task, Project, Pipeline, Workspace, User) can have files attached
- Upload file and create a standalone `Media` record
- Attach / detach existing media to/from any model
- Upload-and-attach in a single request
- Reorder media within a tag group
- Supported aggregate types: image, video, audio, document, other
- 20 MB per-file upload limit
- Tag-based grouping (e.g. `attachments`, `avatar`)

---

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- MySQL 8+

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/PaudelN/tms-b.git
cd tms-b

# 2. Install PHP dependencies
composer install

# 3. Copy and configure the environment file
cp .env.example .env
# Edit .env — set DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL, etc.

# 4. Generate the application key
php artisan key:generate

# 5. Run migrations and seed demo data
php artisan migrate --seed

# 6. Create the storage symlink (required for public file access)
php artisan storage:link

# 7. Start the development server
php artisan serve
```

The API will be available at `http://localhost:8000/api`.

---

## API Overview

All endpoints (except the auth routes) require `Authorization: Bearer <token>` header.

### Auth
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/register` | Register a new user |
| POST | `/api/login` | Log in and receive a Sanctum token |
| POST | `/api/logout` | Invalidate current token |

### Workspaces
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/workspaces` | List workspaces |
| POST | `/api/workspaces` | Create workspace |
| GET | `/api/workspaces/{id}` | Show workspace |
| PUT | `/api/workspaces/{id}` | Update workspace |
| DELETE | `/api/workspaces/{id}` | Delete workspace |
| GET | `/api/workspaces/counts` | Aggregated counts |
| GET | `/api/workspaces/kanban/board` | Kanban board |
| POST | `/api/workspaces/kanban/move` | Move item |
| POST | `/api/workspaces/kanban/reorder` | Reorder items |

### Projects
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/workspaces/{workspace}/projects` | List projects in workspace |
| POST | `/api/workspaces/{workspace}/projects` | Create project |
| GET | `/api/projects/{id}` | Show project |
| POST | `/api/projects/{id}/update` | Update project |
| DELETE | `/api/projects/{id}` | Delete project |
| GET | `/api/workspaces/{workspace}/projects/counts` | Counts |
| GET | `/api/workspaces/{workspace}/projects/kanban/board` | Kanban board |

### Pipelines
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/projects/{project}/pipelines` | List pipelines |
| POST | `/api/projects/{project}/pipelines` | Create pipeline |
| GET | `/api/pipelines/{id}` | Show pipeline |
| POST | `/api/pipelines/{id}/update` | Update pipeline |
| DELETE | `/api/pipelines/{id}` | Delete pipeline |

### Pipeline Stages
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/pipelines/{pipeline}/stages` | List stages |
| POST | `/api/pipelines/{pipeline}/stages` | Create stage |
| GET | `/api/stages/{id}` | Show stage |
| POST | `/api/stages/{id}/update` | Update stage |
| DELETE | `/api/stages/{id}` | Delete stage |
| POST | `/api/pipelines/{pipeline}/stages/reorder` | Reorder stages |

### Tasks
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/pipelines/{pipeline}/tasks` | List tasks in pipeline |
| POST | `/api/pipelines/{pipeline}/tasks` | Create task |
| GET | `/api/tasks/{id}` | Show task |
| POST | `/api/tasks/{id}/update` | Update task |
| DELETE | `/api/tasks/{id}` | Delete task |
| GET | `/api/tasks/my` | Tasks assigned to current user |
| GET | `/api/tasks/all` | All accessible tasks |
| POST | `/api/pipelines/{pipeline}/tasks/kanban/move` | Kanban move |
| POST | `/api/pipelines/{pipeline}/tasks/kanban/reorder` | Kanban reorder |

### Media
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/media` | Upload a file (standalone) |
| GET | `/api/media/{media}` | Show file record |
| PATCH | `/api/media/{media}` | Update alt text |
| DELETE | `/api/media/{media}` | Delete file + record |
| GET | `/api/{type}/{id}/media` | List media on a model |
| POST | `/api/{type}/{id}/media/upload` | Upload + attach to model |
| POST | `/api/{type}/{id}/media/attach` | Attach existing media to model |
| DELETE | `/api/{type}/{id}/media/{media}/detach` | Detach from model |
| PATCH | `/api/{type}/{id}/media/reorder` | Reorder media in a tag |

`{type}` can be: `tasks`, `users`, `projects`, `pipelines`, `workspaces`

---

## Database Schema

```
users
workspaces  ─── workspace_users (pivot)
            └── projects
                    └── pipelines
                            └── pipeline_stages
                                    └── tasks
kanban_orders
media  ─── mediables (polymorphic pivot)
```

---

## Running Tests

```bash
php artisan test
```

---

## Related Repository

- **Frontend (tms-f):** [https://github.com/PaudelN/tms-f](https://github.com/PaudelN/tms-f)

---

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
