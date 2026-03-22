# 📘 TaskFlow Pro Documentation

## ✨ Core Features

### 1. Dashboard & Analytics
- **Live Stats**: Real-time count of pending and completed tasks.
- **Productivity Chart**: A 7-day trend visualization using Chart.js to track your progress.

### 2. Kanban Board (Projects)
- Efficiently manage tasks using a 3-column workflow: **Todo**, **In Progress**, and **Done**.
- Drag-and-drop support for quick status updates.
- Real-time synchronization with the database.

### 3. Activity Center
- Automatic logging of system events.
- Timeline view of all user actions (Notes, Tasks, Documents).
- Helpful for auditing and tracking project history.

### 4. Smart Calendar
- Add tasks directly to specific dates.
- Manage task statuses (Todo, Progress, Done) with one click.
- Monthly navigation and daily filtering.

### 5. System Administration
- **Global Settings**: Change the site name and manage registration from the admin panel.
- **User Management**: Add, edit, or remove users and manage their roles.

## 📁 Project Structure
```text
taskflow/
├── config.php        # Database and Global Helpers
├── index.php         # Main Entry point & Router
├── pages/            # Core feature modules
│   ├── dashboard.php
│   ├── projects.php  # Kanban Board
│   ├── activities.php
│   └── settings.php
├── docs/             # Technical Documentation
└── uploads/          # User-uploaded files & avatars
```

## 🛠️ Technical Details
- **Backend**: PHP Native (PDO) for robust database interaction.
- **Frontend**: Tailwind CSS for modern styling and responsive layouts.
- **Charts**: Chart.js for data visualization.
- **Logging**: Custom `logActivity()` function tracks `user_id`, `action`, and `timestamp`.
