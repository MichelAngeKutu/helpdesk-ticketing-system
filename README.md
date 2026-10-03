# Help Desk Ticketing & Access Management System

A web-based help desk application for logging, assigning, tracking and resolving IT support tickets, with an access-request approval workflow and a full audit log.

Built with **PHP**, **Microsoft SQL Server**, **JavaScript**, **HTML** and **CSS**.

## Features

**Ticketing**
- Users create tickets with a title, description and priority (low, medium, high)
- Technicians and admins assign tickets and move them through open, in progress, resolved and closed
- Everyone involved can comment on a ticket; staff comments are highlighted
- Users only see their own tickets unless they've been granted wider access
- Live search and status filtering on the ticket list (plain JavaScript, no page reload)

**Access management**
- Users request a system permission (for example `tickets.view_all`) and explain why they need it
- Admins approve or deny each request, with an optional note
- A permission is only granted after approval, and admins can't approve their own requests
- The approval and the grant are saved in one database transaction, so they succeed or fail together

**Audit log**
- Records every successful and failed login, logout, ticket creation, comment, status change, assignment and access decision
- Each entry stores the user, the action, the affected record, details and the IP address
- Admin-only page with live filtering

**Security**
- Passwords stored with `password_hash()` (bcrypt), never in plain text
- Every query uses PDO prepared statements to prevent SQL injection
- All output escaped with `htmlspecialchars()` to prevent cross-site scripting (XSS)
- CSRF tokens on every form
- Session ID regenerated at login to prevent session fixation
- Role checks on the server for every protected action
- Validation on both the client (JavaScript) and the server (PHP)

## Roles

| Role | Can do |
|---|---|
| User | Create tickets, comment on their own tickets, request permissions |
| Technician | Everything a user can, plus view all tickets, assign tickets and change status |
| Admin | Everything a technician can, plus approve or deny access requests and view the audit log |

## Tech stack

- PHP 8.1+ with the Microsoft PDO_SQLSRV driver
- Microsoft SQL Server (Express edition works)
- HTML, CSS and plain JavaScript

## Project structure

```
helpdesk/
├── config/
│   └── config.example.php   # copy to config.php and add your DB details
├── database/
│   └── schema.sql           # tables, constraints and starting permissions
├── public/                  # web root: the only folder the browser can reach
│   ├── index.php            # ticket list
│   ├── ticket_new.php       # create a ticket
│   ├── ticket.php           # ticket details, comments, status, assignment
│   ├── access.php           # request access / review requests
│   ├── audit.php            # audit log (admin only)
│   ├── login.php
│   ├── logout.php
│   └── assets/
│       ├── style.css
│       └── app.js           # live filtering and form validation
├── scripts/
│   └── create_user.php      # command-line tool to create accounts
└── src/
    ├── bootstrap.php        # DB connection, sessions, auth, CSRF, audit helpers
    └── views/
        ├── header.php
        └── footer.php
```

## Setup (Windows)

### 1. Install the requirements

1. **SQL Server Express** and **SQL Server Management Studio (SSMS)**
2. **PHP 8.1 or newer** (for example through XAMPP)
3. Microsoft's **PDO_SQLSRV driver** for your PHP version. Copy the matching `php_pdo_sqlsrv_*.dll` into PHP's `ext` folder and add this line to `php.ini`:
   ```
   extension=pdo_sqlsrv
   ```
   You'll also need the **Microsoft ODBC Driver 18 for SQL Server**. Run `php -m` and check that `pdo_sqlsrv` appears in the list.

### 2. Create the database

In SSMS, run:

```sql
CREATE DATABASE HelpDesk;
GO
USE HelpDesk;
GO
```

Then open `database/schema.sql` and run it against the `HelpDesk` database.

### 3. Configure the app

```bash
copy config\config.example.php config\config.php
```

Edit `config/config.php` with your server name, SQL login and password. This file is in `.gitignore`, so it won't be pushed to GitHub.

### 4. Create accounts

There's no public sign-up page, so accounts are created from the command line. You'll be asked for a password (8+ characters):

```bash
php scripts/create_user.php admin "Admin User" admin
php scripts/create_user.php tech1 "Tech One" technician
php scripts/create_user.php jdoe "Jane Doe" user
```

### 5. Run it

```bash
php -S localhost:8000 -t public
```

Open http://localhost:8000 and sign in.

## Try the full workflow

1. Sign in as the **user** and create a ticket.
2. Sign in as the **technician**, assign the ticket to yourself and change its status to "In progress".
3. As the **user**, request the `tickets.view_all` permission.
4. Sign in as the **admin** and approve the request.
5. As the **user**, refresh the ticket list: it now shows all tickets.
6. As the **admin**, open the audit log to see every step recorded.
