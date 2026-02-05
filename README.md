# CareerNest (Job Portal)

PHP + MySQL job portal with role-based dashboards (**Job Seeker**, **Employer**, **Admin**), premium UI styling, in-app messaging, and an automated notifications system.

---

## Tech Stack

- **Backend**: PHP (procedural) + MySQLi prepared statements
- **Database**: MySQL / MariaDB
- **Frontend**: HTML/CSS + Font Awesome
- **Local Dev**: XAMPP (Apache + MySQL)

---

## Requirements

- PHP **8.x** recommended (7.4+ may work)
- MySQL **5.7+** / MariaDB **10+**
- Apache (XAMPP recommended)
- A database created for the app (default: `job_portal_db`)

---

## Project Structure

```
job-portal/
  assets/
    css/style.css
    img/
  core/
    db.php            # database connection
    functions.php     # auth + helpers + schema-safe utilities
  includes/
    header.php
    footer.php
  uploads/
    resumes/          # uploaded CV files
    logos/            # uploaded company logos
  *.php               # pages
```

---

## Local Setup (XAMPP)

1. Install **XAMPP**
2. Copy the project into:
   - `C:\xampp\htdocs\job-portal`
3. Start in XAMPP:
   - **Apache**
   - **MySQL**
4. Create the database:
   - Open `http://localhost/phpmyadmin`
   - Create a DB named **`job_portal_db`**
5. Configure DB credentials (if needed):
   - Edit `core/db.php`

Default values in `core/db.php`:

```php
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$dbname  = 'job_portal_db';
```

6. Open the app:
   - `http://localhost/job-portal/`

---

## Database Notes

### Minimum required tables

This project expects a MySQL schema to exist for the core portal features. At minimum:

- `users`
- `jobs`
- `applications`
- `seeker_profiles` (for seekers)
- `employer_profiles` (for employers)

The codebase is **schema-aware** in many places and will adapt if some optional columns exist or not (using helper functions like `tableExists()` / `tableHasColumn()`).

### Auto-created tables (Messages & Notifications)

The following tables are **auto-created at runtime** (if missing) when the feature is used:

- `messages`
- `notifications`

This is implemented in:

- `core/functions.php`
  - `ensureMessagesTable($conn)`
  - `ensureNotificationsTable($conn)`

#### Shared hosting note (InfinityFree / restricted DB users)

Some shared hosts restrict `CREATE TABLE`. If table auto-creation fails, create them manually using phpMyAdmin.

**Manual SQL – `messages`:**

```sql
CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sender_id INT NOT NULL,
  receiver_id INT NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  INDEX idx_messages_sender (sender_id),
  INDEX idx_messages_receiver (receiver_id),
  INDEX idx_messages_read (receiver_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Manual SQL – `notifications`:**

```sql
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user (user_id),
  INDEX idx_notifications_read (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## User Roles

The app uses a `users.role` field.

- **seeker**
  - apply for jobs
  - manage seeker profile
  - view interview schedule
  - message employers

- **employer**
  - post jobs
  - review applications
  - update application status
  - schedule interviews
  - message seekers

- **admin**
  - access `admin.php`
  - manage users, jobs, applications, reports, payments, audit logs

---

## Key Pages

- **Public**
  - `index.php` (home)
  - `jobs.php` (job list)
  - `job-details.php` (job view + apply)
  - `about.php`, `news.php`, `contact.php`

- **Auth**
  - `register.php`
  - `login.php`
  - `logout.php`

- **Dashboards**
  - `dashboard.php` (role-based dashboard)
  - `profile.php` (profile editor)
  - `settings.php`

- **Employer**
  - `post-job.php`
  - `view-applications.php` (manage applicants for a job)
  - `seekers.php` (browse job seekers)

- **Seeker**
  - `my-applications.php`
  - `saved-jobs.php`
  - `save-job.php`

- **Comms**
  - `messages.php` (in-app messages)
  - `notifications.php` (activity notifications)
  - `interviews.php` (interview management)

- **Admin**
  - `admin.php?section=dashboard|users|jobs|applications|reports|payments|audit`

---

## In-App Messages (Real DB data)

**Page:** `messages.php`

### How it works

- Messages are stored in the `messages` table.
- Threads are built from existing DB rows (no hard-coded examples).
- Unread messages are tracked via a read column (`is_read` / `read` / `seen`).
- When you open a conversation, incoming messages are marked as read.

### Unread badge in the header

The navigation bar shows a real unread count badge using:

- `getUnreadMessageCount($conn, $userId)`

---

## Notifications (Real DB data + Automated)

**Page:** `notifications.php`

### How it works

- Notifications are stored in the `notifications` table.
- The app is schema-safe and can adapt to different column names:
  - title: `title` / `subject`
  - message: `message` / `body` / `content`
  - read flag: `is_read` / `read` / `seen`
  - created time: `created_at` / `created`

### Unread badge in the header

The navigation bar shows a real unread count badge using:

- `getUnreadNotificationCount($conn, $userId)`

### Automatic notification triggers

Notifications are created automatically on key actions:

- **New message** (receiver gets “New message” notification)
- **New application submitted** (employer gets “New application” notification)
- **Application status updated** (seeker gets “Application update” notification)
- **Interview scheduled** (seeker gets “Interview scheduled” notification)
- **Interview rescheduled/cancelled/completed** (other party gets notified)

Implementation helper:

- `createNotification($conn, $userId, $title, $message)`

---

## Uploads / File Permissions

The app stores uploaded files in:

- `uploads/resumes/`
- `uploads/logos/`

Make sure these folders are writable by PHP in your environment.

---

## Security Notes

- Do **not** use the default `root` MySQL user in production.
- Use a dedicated DB user with least privileges.
- This project uses prepared statements for SQL safety in most places.

---

## Troubleshooting

- **DB connection error**
  - Verify `core/db.php` credentials
  - Ensure MySQL is running
  - Ensure the database exists (`job_portal_db` by default)

- **Messages/Notifications not appearing**
  - Confirm tables exist (`messages`, `notifications`)
  - On shared hosts, create them manually using the SQL above

- **Uploads not working**
  - Ensure `uploads/` subfolders exist and are writable

---

## License

Internal / educational project (add a license if you plan to publish).