# Campus Clinic Appointment Management System (CCAMS)
### USTED University, Kumasi — Academic Group Project

A complete, responsive, and secure web application built with **PHP 8+**, **MySQL/MariaDB (PDO)**, **HTML5**, **CSS3**, **Bootstrap 5**, and **JavaScript** for managing student clinic appointments, doctor availability, scheduling, cancellations, rescheduling, and notifications at **USTED University**.

---

## Core Features

- **Public Portal**: University clinic homepage, services overview, clinic policies & operating hours, contact desk inquiry form, student registration, and secure portal login.
- **Role-Based Access Control (RBAC)**: Separate, protected dashboards for **Students**, **Doctors**, and **Administrators**.
- **Transaction-Safe Booking Engine**: Uses MySQL `InnoDB` transactions and `SELECT ... FOR UPDATE` row-level locking to prevent concurrent double-booking race conditions.
- **Cancellation & Rescheduling Rules**: Preserves historical cancelled records in `appointments`, releases future slots cleanly, and enforces the 2-hour clinic cutoff window.
- **Decoupled Notification Service**: Supports in-app alerts, a mock development adapter (`simulated`), and an optional external SMS sandbox adapter (`queued` / `failed` without fabricating delivery).
- **Security Hardening**: `password_hash()` / `password_verify()`, PDO prepared statements (`EMULATE_PREPARES = false`), HTML escaping via `e()`, per-session CSRF tokens, and session ID regeneration on login.

---

## Project Folder Structure

```text
ccams/
├── admin/
│   ├── add_doctor.php
│   ├── dashboard.php
│   ├── edit_doctor.php
│   ├── manage_appointments.php
│   ├── manage_doctors.php
│   ├── manage_slots.php
│   ├── manage_users.php
│   └── notifications.php
├── assets/
│   ├── css/style.css
│   └── js/script.js
├── config/
│   ├── database.example.php
│   └── database.php
├── database/
│   └── campus_clinic.sql
├── doctor/
│   ├── appointment_details.php
│   ├── dashboard.php
│   ├── my_appointments.php
│   └── profile.php
├── docs/
│   ├── INSTALLATION_GUIDE.md
│   └── PROJECT_DOCUMENTATION.md
├── includes/
│   ├── auth.php
│   ├── csrf.php
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── services/
│   ├── AppointmentService.php
│   ├── AuthService.php
│   └── NotificationService.php
├── student/
│   ├── appointment_details.php
│   ├── book_appointment.php
│   ├── dashboard.php
│   ├── doctors.php
│   ├── my_appointments.php
│   ├── notifications.php
│   └── profile.php
├── tests/
│   ├── bootstrap.php
│   └── CCAMSIntegrationTest.php
├── about.php
├── composer.json
├── contact.php
├── index.php
├── login.php
├── logout.php
├── phpunit.xml
├── register.php
└── README.md
```

---

## Quick Installation (XAMPP)

1. Copy the project folder into `C:\xampp\htdocs\ccams` (or `/Applications/XAMPP/htdocs/ccams`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and import `database/campus_clinic.sql`.
4. (Optional) Copy `config/database.example.php` to `config/database.local.php` if your MySQL username/password differs from XAMPP defaults (`root` / empty password).
5. Open `http://localhost/ccams/` in your browser.

For full setup, Composer, and PHPUnit instructions, see [`docs/INSTALLATION_GUIDE.md`](docs/INSTALLATION_GUIDE.md).

---

## Fictional Demonstration Accounts

| Role | Email | Password |
| :--- | :--- | :--- |
| **Student** | `kwabena.asare@st.usted.edu.gh` | `Student@2026` |
| **Doctor** | `kwame.mensah@usted.edu.gh` | `Doctor@2026` |
| **Administrator** | `admin@usted.edu.gh` | `Admin@2026` |

---

## Running Automated PHPUnit Tests

```bash
composer install
vendor/bin/phpunit
```

---

## Group Project Team & GitHub Workflow (5 Members)

| Member | Primary Responsibilities |
| :--- | :--- |
| **Member 1** | Project coordination, Sprint planning, GitHub repository & PR management |
| **Member 2** | PHP backend architecture, authentication, CSRF, and RBAC (`includes/`, `services/AuthService.php`) |
| **Member 3** | MySQL schema design, seed data, and transaction-safe booking logic (`database/`, `services/AppointmentService.php`) |
| **Member 4** | HTML5, CSS3, Bootstrap 5, and JavaScript UI across public and role dashboards (`assets/`, `includes/header.php`, pages) |
| **Member 5** | Notification adapters, PHPUnit test suite, test plan execution, and software documentation (`services/NotificationService.php`, `tests/`, `docs/`) |

### Recommended Git Branching Workflow
```bash
git checkout -b feature/member2-auth-rbac
git add includes/auth.php includes/csrf.php login.php register.php
git commit -m "feat(auth): implement PDO login, registration, CSRF, and RBAC"
git push origin feature/member2-auth-rbac
```
