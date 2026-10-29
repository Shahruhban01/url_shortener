# URL Shortener

A URL shortener built with Laravel 12 and MySQL. It supports multiple companies, user roles, team invitations, and click tracking.

## Features

- Login and logout
- Three roles: SuperAdmin, Admin, and Member
- Company creation and team invitations
- Short URL creation for Admins and Members
- Redirects from short URLs to their original URLs
- Click count tracking
- URL listings based on company and user permissions
- Viewing and cancelling pending invitations

## Tech Stack

- PHP 8.3
- Laravel 12
- MySQL
- Laravel Breeze (Blade)
- Eloquent ORM
- HTML and CSS

## Setup

### Requirements

Make sure PHP, Composer, MySQL, and Git are installed. XAMPP can be used to run PHP and MySQL on Windows.

### 1. Clone the repository

```bash
git clone https://github.com/shahruhban01/url-shortener.git
cd url-shortener
```

### 2. Install dependencies

```bash
composer install
```

### 3. Set up the environment

Create your local environment file.

**Windows PowerShell**
```powershell
Copy-Item .env.example .env
```

**Linux/macOS**
```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

### 4. Configure MySQL

Create a database named `url_shortener` in MySQL.

Update these values in `.env` to match your local database setup:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=url_shortener
DB_USERNAME=root
DB_PASSWORD=
```

### 5. Configure invitation emails

For local testing, use Laravel's log mailer so invitation emails are written to the application log instead of being sent.

Add these settings to `.env`:

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

After creating an invitation, check `storage/logs/laravel.log` for the invitation link.

### 6. Create the SuperAdmin

Add the credentials for the SuperAdmin seeder to `.env`:

```env
SUPERADMIN_EMAIL=superadmin@example.com
SUPERADMIN_PASSWORD=change-this-password
```

The seeder creates the account using a raw SQL insert. Use a local password for development and don't commit real credentials.

### 7. Run migrations and seed the database

```bash
php artisan migrate --seed
```

Use a development database for the initial setup. Avoid `migrate:fresh` if the database contains data you need.

### 8. Start the application

```bash
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser and log in with the SuperAdmin credentials you configured.

## Roles and Permissions

| Feature | SuperAdmin | Admin | Member |
|---|---|---|---|
| Create companies | Yes | No | No |
| Invite the first Admin to a company | Yes | No | No |
| Invite Admins or Members to a company | No | Yes | No |
| View team information | All companies | Own company | No |
| Create short URLs | No | Yes | Yes |
| View short URLs | All companies | Own company | Own URLs |
| Manage invitations | All companies | Own company | No |

## Short URLs

When a user creates a short URL, the application generates a unique code and saves it with the original URL, company, and creator.

Opening the short URL redirects to the original destination and increments its click count. The redirect is public, but the URL management pages require login and enforce role-based access.

## Tests

Run the test suite with:

```bash
php artisan test
```

The tests cover URL creation by Admins and Members, the SuperAdmin restriction, company-level and user-level URL visibility, and public redirects with click tracking.

## AI Usage

ChatGPT was used during development to explain Laravel concepts, help troubleshoot errors, review implementation choices, and suggest tests and improvements. The code and final changes were reviewed during development.

## Notes
Super Admin credentials For Testing :- Email: shahruhban01@gmail.com Password: password123

## License

Prepared as part of a backend developer assignment.
