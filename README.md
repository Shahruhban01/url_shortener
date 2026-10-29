# URL Shortener

A multi-company URL shortening application built with **Laravel 12, PHP, and MySQL**. The application supports role-based authentication, company management, invitation-based user registration, short URL creation, click tracking, and access-controlled URL listings.

## Features

- **Authentication:** Login and logout using Laravel Breeze.
- **Role-based access control:** SuperAdmin, Admin, and Member.
- **Company management:** SuperAdmin can create companies and invite their first Admin.
- **Team invitations:** Admins can invite Admins or Members to their own company.
- **Invitation acceptance:** Secure, time-limited invitation links with password creation.
- **URL shortening:** Admins and Members can create short URLs.
- **URL redirection:** Short URLs redirect users to their original destinations.
- **Click tracking:** Records the number of redirects for each short URL.
- **Access control:** URL listings are restricted according to the user's role and ownership.
- **Invitation management:** Authorized users can view and cancel pending invitations.

## Technology Stack

- PHP 8.3
- Laravel 12
- MySQL
- Laravel Breeze with Blade
- Eloquent ORM
- HTML and CSS

## Requirements

Install the following before setting up the application:

- PHP 8.3 or a compatible Laravel-supported version
- Composer
- MySQL
- Git
- A PHP environment such as XAMPP, if using Windows

## Local Setup

### 1. Clone the repository

```bash
git clone https://github.com/shahruhban01/url-shortener
cd url_shortner
```

### 2. Install dependencies

```bash
composer install
```

### 3. Configure environment variables

Copy the example environment file.

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Linux/macOS:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

### 4. Configure the database

Create a MySQL database named `url_shortener`.

Update the database configuration in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=url_shortener
DB_USERNAME=root
DB_PASSWORD=
```

Update the username and password according to your local MySQL configuration.

### 5. Configure invitation emails

For local development, Laravel can write invitation emails to its log.

Add or update these settings in `.env`:

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

After creating an invitation, check `storage/logs/laravel.log` for the invitation link.

### 6. Configure the SuperAdmin account

Set the credentials used by the SuperAdmin database seeder in `.env`:

```env
SUPERADMIN_EMAIL=superadmin@example.com
SUPERADMIN_PASSWORD=replace-with-a-strong-password
```

Use a strong local password and do not commit real credentials to GitHub.

The SuperAdmin account is created through the database seeder using a raw SQL insert.

### 7. Run database migrations and seed the SuperAdmin

```bash
php artisan migrate --seed
```

This creates the required database tables and runs the configured database seeders.

**Warning:** Use a fresh development database for initial setup. Do not use `migrate:fresh` on a database containing data you need to preserve.

### 8. Start the application

```bash
php artisan serve
```

Open the URL shown in your terminal, usually:

http://127.0.0.1:8000

Log in using the SuperAdmin credentials configured in your local `.env` file.

## Role-Based Access

| Feature | SuperAdmin | Admin | Member |
|---|---|---|---|
| Create companies | Yes | No | No |
| Invite the first company Admin | Yes | No | No |
| Invite company Admins or Members | No | Yes | No |
| View team information | All companies | Own company | No |
| Create short URLs | No | Yes | Yes |
| View short URLs | All companies | Own company | Own URLs |
| Manage invitations | All companies | Own company | No |

Sales and Manager are listed in the original assignment's role requirements but are not currently implemented as functional roles in this application.

## Short URL Behavior

When an authorized user creates a short URL, the application generates a unique code and stores the original URL, company, creator, and click count.

Opening a valid short URL redirects the visitor to the original destination and increments its click count.

The short URL redirects publicly, while access to the URL management listings requires authentication and the appropriate role.

## Testing

Run the automated test suite with:

```bash
php artisan test
```

The main authorization and functional scenarios to verify include:

- SuperAdmin cannot create short URLs.
- Admin and Member can create short URLs.
- SuperAdmin can view URLs across companies.
- Admin can view URLs belonging only to their company.
- Member can view only URLs they created.
- Short URL codes redirect to the original URL.
- Redirecting increments the click count.
- Admins cannot access or cancel invitations belonging to another company.
- Expired or previously accepted invitations cannot be used again.

## AI Usage Disclosure

AI tools were used as development assistance during this assignment.

- **ChatGPT:** Helped explain Laravel concepts, review code, troubleshoot routing and authorization issues, and suggest improvements to validation, invitation handling, and Blade templates.
- **IDE AI agent (if used):** Assisted with the specific code changes requested during development. Describe the actual changes it made before submitting.

The application logic, implementation decisions, testing, and final code should be reviewed and understood by the developer before submission.

## Notes

- Never commit `.env`, production credentials, or other secrets.
- Keep `.env.example` updated with the environment variables required for setup, without real credentials.
- Configure an appropriate production mail service before deploying the application.
- Verify that all authorization tests pass before submission.

## License

This project was developed as part of a backend developer assignment.