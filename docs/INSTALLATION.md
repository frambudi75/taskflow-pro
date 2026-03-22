# 🚀 TaskFlow Pro - Installation Guide

Follow these steps to set up TaskFlow Pro on your local machine or server.

## 📋 Prerequisites
- **Web Server**: Apache (XAMPP, WAMP, or Laragon recommended)
- **PHP**: Version 7.4 or higher
- **Database**: MySQL / MariaDB
- **Browser**: Modern browser (Chrome, Firefox, Edge, Safari)

## 🛠️ Step-by-Step Setup

### 1. Clone or Extract
Place the `taskflow` folder into your web server's root directory:
- **XAMPP**: `C:\xampp\htdocs\taskflow`
- **Linux**: `/var/www/html/taskflow`

### 2. Database Configuration
1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a new database named `taskflow`.
3. Import the database schema:
   - Click the **Import** tab.
   - Choose the `database.sql` file from the project root.
   - Click **Go**.

### 3. Application Settings
Open `config.php` in your text editor and update the database credentials if necessary:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'taskflow');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Run the Application
Open your browser and navigate to:
`http://localhost/taskflow`

---

## 🐳 Docker Setup (Recommended)
If you have **Docker** and **Docker Compose** installed, you can set up the entire environment with a single command:

1. Open your terminal in the project root.
2. Run:
   ```bash
   docker-compose up -d --build
   ```
3. The application will be available at:
   `http://localhost:8080`

**Note**: The database will be automatically created and imported on the first run.

---
**Default Admin Credentials:**
- **Email**: `habib@omit.avi.id`
- **Password**: `admin123` (Please change this immediately after login)
