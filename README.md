# Mindly – Online Course Platform

Mindly is a personal-development course platform built with plain PHP and MySQL. Users can register, browse and search courses, purchase them through a simulated checkout, and track their enrolled courses. An admin panel handles course and user management.

> **Simulation project.** Mindly is not a real company or a live service – it was built as a university web programming project to simulate an online course platform. Company details, course content, student counts and payments are fictional. The interface is in Turkish.

<!-- Add screenshots here, e.g.:
![Landing page](screenshots/landing.png)
![Course catalog](screenshots/catalog.png)
-->

## Features

**User side**
- Registration and login with hashed passwords (`password_hash` / `password_verify`)
- Session handling with session ID regeneration on login, and logout
- Token-based password reset flow (single-use tokens that expire after 1 hour; e-mail sending is simulated – see below)
- Course catalog with search, level filter, sorting and pagination
- "My Courses" page with sorting, lesson-by-lesson progress tracking ("complete a lesson") and the option to leave a course
- Dashboard with enrolled courses, lessons completed this month and active days in the last 30 days
- Checkout page with an interactive 3D credit card preview – payment is simulated by design (card format is validated, the purchase is recorded, no card data is stored and no payment provider is involved)
- Course video playback in a modal for courses that have a video

**Admin panel**
- Add, edit and delete courses, including video upload (MP4 / WebM / MOV, type and size checks)
- Add and delete users
- Overview statistics (total courses, users and purchases)

**Security basics**
- All database queries use PDO prepared statements
- Sorting and filter parameters are checked against whitelists
- User-supplied output is escaped with `htmlspecialchars`
- Credentials live in a git-ignored `config.php`

## Tech Stack

- PHP 7.4+ (no framework)
- MySQL / MariaDB via PDO
- HTML, CSS, JavaScript, Bootstrap
- Developed and tested locally with XAMPP

## Project Structure

```
├── Mindly.php            # Landing page (with registration form)
├── kayit.php / giris.php # Register / login
├── sifreyenileme.php     # Request a password reset
├── yenisifre.php         # Set a new password with a reset token
├── anasayfa.php          # User dashboard
├── tum_kurslar.php       # Course catalog
├── kurslar.php           # My courses
├── satin_al.php          # Checkout
├── cikis.php             # Logout
├── admin_giris.php       # Admin login
├── admin_panel.php       # Admin panel
├── db.php                # PDO connection
├── config.example.php    # Configuration template
├── database/mindly.sql   # Database schema + sample courses
├── images/               # Static images
└── uploads/              # Uploaded course videos (git-ignored)
```

## Getting Started

1. Install [XAMPP](https://www.apachefriends.org/) (or any PHP 7.4+ and MySQL setup).
2. Clone the repository into `htdocs`:
   ```bash
   git clone https://github.com/minaozcn/mindly-course-platform.git Mindly
   ```
3. Start Apache and MySQL, then import `database/mindly.sql` in phpMyAdmin.
4. Copy `config.example.php` to `config.php` and set your database and admin credentials.
5. Open `http://localhost/Mindly/Mindly.php`.

## What Is Simulated

- **Payments:** the checkout validates the card format and records the purchase. No payment provider is involved and no card data is stored.
- **Password reset e-mail:** instead of sending an e-mail, the reset token is written to the PHP error log, so the reset link can be built from it for testing.
- **Content and numbers:** sample courses, student counts and the company timeline on the landing page are sample data.

## Known Limitations

- Lessons are marked as completed manually with a button; watching a video does not update progress automatically.
- The admin login uses a single username/password from `config.php` rather than an admin table.
- Forms have no CSRF protection.

## Author

Mina – [GitHub](https://github.com/minaozcn)
