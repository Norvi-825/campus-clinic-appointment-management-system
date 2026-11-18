# CCAMS Installation & Local Setup Guide (XAMPP + VS Code + PHPUnit)

## 1. Prerequisites
- **XAMPP** with **PHP 8.0+** and **MariaDB / MySQL**
- **Visual Studio Code** (recommended extensions: *PHP Intelephense*, *MySQL*)
- **Git** for version control
- **Composer** (optional, required only for running PHPUnit locally)

---

## 2. Step-by-Step XAMPP Setup

1. **Place the Project in `htdocs`**
   - Copy or clone the repository into:
     - Windows: `C:\xampp\htdocs\ccams`
     - macOS: `/Applications/XAMPP/htdocs/ccams`
     - Linux: `/opt/lampp/htdocs/ccams`

2. **Start Apache & MySQL**
   - Launch the XAMPP Control Panel and click **Start** on both **Apache** and **MySQL**.

3. **Create & Seed the Database**
   - Visit `http://localhost/phpmyadmin`.
   - Click **Import**, choose `database/campus_clinic.sql`, and click **Go**.
   - This creates the `campus_clinic` database, all 5 tables, indexes, foreign keys, and fictional USTED University demonstration records.

4. **Configure Database & Optional Sandbox Credentials**
   - By default, `config/database.php` connects to `127.0.0.1`, database `campus_clinic`, user `root`, empty password `""`.
   - To override credentials or add an SMS sandbox API key without modifying tracked files, create `config/database.local.php` (already listed in `.gitignore`):
     ```php
     <?php
     return [
         'db' => [
             'host' => '127.0.0.1',
             'name' => 'campus_clinic',
             'user' => 'root',
             'pass' => '',
         ],
         'notifications' => [
             'default_external_mode' => 'mock', // or 'sms_sandbox'
             'sms_sandbox_endpoint' => '',
             'sms_sandbox_api_key' => '',
         ],
     ];
     ```

5. **Launch the Application**
   - Open your browser to `http://localhost/ccams/index.php`.

---

## 3. Running Automated PHPUnit Tests

1. Open a terminal inside the project directory (`C:\xampp\htdocs\ccams`).
2. Install PHPUnit via Composer:
   ```bash
   composer install
   ```
3. Ensure MySQL is running and `campus_clinic` has been imported.
4. Execute the test suite:
   ```bash
   vendor/bin/phpunit
   ```
