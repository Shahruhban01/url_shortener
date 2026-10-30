# Security Audit Report: Laravel Multi-Company URL Shortener

**Date:** October 30, 2026  
**Auditor:** Senior Application Security Auditor & Laravel Backend Engineer  
**Audit Scope:** Full Application Source Code Review (Read-Only)  
**Target Application:** Laravel 12 Multi-Company URL Shortener  
**Repository Working Directory:** `f:\Sembark\url_shortner`  

---

## 1. Executive Summary

A comprehensive, read-only security audit was performed on the Laravel multi-company URL shortener application. The target system provides multi-tenant URL shortening services with three distinct user roles (**SuperAdmin**, **Admin**, and **Member**), invitation-driven onboarding, public URL redirection, and click tracking.

### Overall Security Posture
The application demonstrates strong foundational design in several core areas:
- **Authorization & Multi-Tenancy:** Role-based access control (RBAC) via `RoleMiddleware` and tenant scoping on queries consistently prevent horizontal privilege escalation (IDOR) and cross-company URL/invitation access.
- **Invitation Token Security:** Invitation tokens are generated with 64 cryptographically secure random characters and stored exclusively as SHA-256 hashes in the database. Token acceptance employs pessimistic database locking (`lockForUpdate()`) inside atomic transactions, effectively thwarting acceptance race conditions.
- **CSRF & Output Encoding:** CSRF protection is properly applied across all state-changing web routes, and Blade templating uses standard escaping syntax without raw unescaped output (`{!! !!}`).
- **Self-Registration Disabled:** Public self-registration routes from Laravel Breeze have been explicitly commented out and disabled, restricting onboarding to the verified invitation workflow.

### Key Risks and Vulnerabilities
Despite these solid fundamentals, the audit identified several significant vulnerabilities and operational security risks that must be addressed:
1. **Critical Secret Exposure in Repository (`APP_KEY`):** `.env.example` contains a static, pre-populated base64 application key. In conjunction with Laravel 12's local disk configuration (`'serve' => true` in `config/filesystems.php`), any deployment running with this key allows unauthenticated external attackers to forge signed URLs for arbitrary file uploads and downloads.
2. **Plaintext Invitation Token Logging:** `.env.example` configures `MAIL_MAILER=log`, causing raw, unhashed 64-character invitation URLs to be written to persistent log files (`storage/logs/laravel.log`). This completely circumvents token hashing for anyone with access to application logs.
3. **User / Email Enumeration & Missing Throttling on Password Reset:** The password reset request endpoint differentiates responses between registered and unregistered emails and lacks rate limiting, allowing automated enumeration of registered users.
4. **Denial of Service / Database Row-Lock Contention on URLs:** Neither short URL creation (`POST /short-urls`) nor public redirection (`GET /{code}`) has rate limiting. Public redirects perform synchronous `clicks` increments directly on the `short_urls` table, creating database write lock contention and susceptibility to click count manipulation.
5. **Unhandled Foreign Key Constraint Violation on Account Deletion:** When a user with associated short URLs or invitations deletes their account via `ProfileController::destroy`, the database foreign key `restrictOnDelete()` constraint triggers an unhandled 500 database error after logging the user out.

### Audit Scope & Limitations
This audit was performed strictly as a read-only source-code and configuration review. In accordance with the mandate, no application files or databases were altered. Dynamic test execution via `php artisan test` was withheld because the testing configuration (`phpunit.xml`) has in-memory SQLite commented out and the runtime environment lacks the `pdo_sqlite` driver; executing tests against the configured MySQL database would invoke `RefreshDatabase` and destroy application state.

---

## 2. Project and Attack Surface

### 2.1 Authentication Architecture
- **Framework & Starter Kit:** Built on Laravel 12.70.2 using Laravel Breeze scaffolding.
- **Session Lifecycle:** Managed via database session driver (`SESSION_DRIVER=database`). Sessions are regenerated upon login (`$request->session()->regenerate()`) and invalidated on logout (`$request->session()->invalidate()`).
- **Account Registration:** Public self-registration (`/register`) is disabled in `routes/auth.php`. New accounts can only be provisioned through the invitation acceptance flow (`POST /invitations/{token}`).
- **Authentication Throttling:** Login requests are protected by `LoginRequest` enforcing 5 attempts per minute keyed on transliterated email and client IP.
- **Password Reset:** Standard token broker via `password_reset_tokens` table.

### 2.2 Roles and Role-Based Access Control (RBAC)
The application defines three roles in `users.role`:
- **SuperAdmin:**
  - `company_id` is `null`.
  - Can create a company and invite its first Admin (`GET|POST /companies/create`, `/companies/invite-admin`).
  - Can view all short URLs across all companies (`GET /short-urls`).
  - Can view all companies and their members (`GET /team`).
  - Can view and cancel all pending invitations across all companies (`GET|DELETE /invitations`).
  - **Explicitly prevented** from creating short URLs.
- **Admin:**
  - Belongs to a specific company (`company_id` is set).
  - Can invite Admins and Members to their own company (`GET|POST /company/invitations/create`, `/company/invitations`).
  - Can view and cancel pending invitations for their company only.
  - Can view team members of their company only (`GET /team`).
  - Can view all short URLs created within their company (`GET /short-urls`).
  - Can create short URLs (`GET|POST /short-urls/create`, `/short-urls`).
- **Member:**
  - Belongs to a specific company (`company_id` is set).
  - Can create short URLs (`GET|POST /short-urls/create`, `/short-urls`).
  - Can view only short URLs they personally created (`GET /short-urls`).
  - Restricted from administrative views (cannot access `/companies/*`, `/company/*`, `/team`, `/invitations`).

### 2.3 Company Boundaries & Multi-Tenancy
Tenant isolation is enforced through database foreign keys (`company_id`) and explicit server-side query filters:
- In `ShortUrlController::index`: Admins query `where('company_id', $user->company_id)`, while Members query `where('user_id', $user->id)`.
- In `TeamController::index`: Admins load `Company::with('users')->findOrFail($user->company_id)`.
- In `InvitationController::index`: Admins query `Invitation::where('company_id', $user->company_id)`.
- In `InvitationController::cancel`: Admins verify `$invitation->company_id === $user->company_id`.

### 2.4 Invitation Workflow
1. SuperAdmin invites a company Admin (`storeCompanyInvitation`), creating a `Company` record and an `Invitation` record in a transaction.
2. Company Admin invites an Admin or Member (`storeCompanyMemberInvitation`), creating an `Invitation` bound to their `company_id`.
3. An unhashed token (`Str::random(64)`) is generated. The SHA-256 hash (`hash('sha256', $plainToken)`) is stored in `invitations.token_hash`.
4. Plaintext invitation URL is sent via mailer.
5. Invitee navigates to `GET /invitations/{token}`: Token hash is validated; expired, accepted, or missing invitations return 404.
6. Invitee submits `POST /invitations/{token}`: Token hash is verified under a pessimistic row lock (`lockForUpdate()`), a new `User` is created, and `invitations.accepted_at` is timestamped.

### 2.5 URL Creation, Redirection, and Click Tracking
- **Creation:** Authenticated Admins/Members submit `original_url`. An 8-character alphanumeric string (`Str::random(8)`) is generated.
- **Public Redirection:** `GET /{code}` matches `[A-Za-z0-9]{8}`, increments `clicks` synchronously, and executes `redirect()->away($shortUrl->original_url)`.

### 2.6 Exposed Attack Surface (Route Inventory)

| Route / URI | HTTP Method | Middleware | Authorization Scope | Target Action |
|---|---|---|---|---|
| `/` | GET | None | Public | Redirects to login |
| `/login` | GET, POST | `guest`, `throttle` (on POST) | Public / Guest | Login form & authentication |
| `/logout` | POST | `auth` | Authenticated | Destroy session |
| `/forgot-password` | GET, POST | `guest` (No throttle on POST) | Public / Guest | Request password reset link |
| `/reset-password/{token}` | GET, POST | `guest` | Public / Guest | Password reset submission |
| `/dashboard` | GET | `auth`, `verified` | Authenticated & Verified | Basic dashboard view |
| `/profile` | GET, PATCH, DELETE | `auth` | Authenticated | Edit/update/delete account |
| `/short-urls` | GET | `auth`, `role:super_admin,admin,member` | Authenticated (All roles) | URL listing (scoped by role) |
| `/short-urls/create` | GET | `auth`, `role:admin,member` | Admin, Member | URL creation form |
| `/short-urls` | POST | `auth`, `role:admin,member` | Admin, Member | Store new short URL |
| `/{code}` | GET | None | Public | Redirection & click counter |
| `/companies/create` | GET | `auth`, `role:super_admin` | SuperAdmin | Create company form |
| `/companies/invite-admin`| POST | `auth`, `role:super_admin` | SuperAdmin | Store company & invite admin |
| `/company/invitations/create` | GET | `auth`, `role:admin` | Admin | Member invitation form |
| `/company/invitations` | POST | `auth`, `role:admin` | Admin | Store member invitation |
| `/invitations` | GET | `auth`, `role:super_admin,admin` | SuperAdmin, Admin | View invitations |
| `/invitations/{id}` | DELETE | `auth`, `role:super_admin,admin` | SuperAdmin, Admin (scoped) | Cancel invitation |
| `/invitations/{token}` | GET | None | Public | Invitation acceptance form |
| `/invitations/{token}` | POST | `throttle:10,1` | Public | Process invitation acceptance |
| `/team` | GET | `auth`, `role:super_admin,admin` | SuperAdmin, Admin (scoped) | Team management view |
| `/admin-area` | GET | `auth`, `role:admin` | Admin | Static placeholder string |
| `/storage/{path}` | GET, PUT | None (Signed URL required) | Public with valid signature | Local storage serve/receive |

---

## 3. Findings Summary

| Finding ID | Title | Severity | Status | Affected Component |
|---|---|---|---|---|
| **SEC-01** | Hardcoded Application Encryption Key Committed in `.env.example` | **Critical** | Confirmed | Configuration (`.env.example`) |
| **SEC-02** | Plaintext Invitation Token Leakage into Application Logs | **High** | Confirmed | Email / Invitations (`InvitationController.php`, `.env.example`) |
| **SEC-03** | User and Email Enumeration via Password Reset Endpoint | **Medium** | Confirmed | Authentication (`PasswordResetLinkController.php`, `routes/auth.php`) |
| **SEC-04** | Missing Request Throttling on Short URL Creation and Public Redirects | **Medium** | Confirmed | URL Management (`ShortUrlController.php`, `routes/web.php`) |
| **SEC-05** | Unhandled Foreign Key Constraint Violation on User Account Deletion | **Medium** | Confirmed | Profile & Database (`ProfileController.php`, Migrations) |
| **SEC-06** | Short Code Generation TOCTOU Race Condition on Collision | **Low** | Confirmed | URL Management (`ShortUrlController.php`) |
| **SEC-07** | Permissive URL Scheme Validation Allowing Non-HTTP Protocols | **Low** | Confirmed | URL Validation (`ShortUrlController.php`) |
| **SEC-08** | Missing Email Verification State on Invitation Acceptance | **Low** | Confirmed | Authentication / Workflow (`InvitationController.php`) |
| **SEC-09** | Unbounded Invitation Creation & Missing Pending Uniqueness Constraint | **Low** | Confirmed | Invitations (`InvitationController.php`) |
| **SEC-10** | Inconsistent Password Complexity Requirements Across Workflows | **Low** | Confirmed | Authentication (`InvitationController.php`, `NewPasswordController.php`) |
| **SEC-11** | Missing Browser Security Headers and Reverse Tabnabbing Weakness | **Low** | Confirmed | Browser Security (`bootstrap/app.php`, Views) |
| **SEC-12** | Default SuperAdmin Seed Credentials and Fragile Deletion State | **Informational** | Confirmed | Seeders (`SuperAdminSeeder.php`, `.env.example`) |
| **SEC-13** | Orphaned Laravel Breeze Scaffolding and Incomplete Route Artifacts | **Informational** | Confirmed | Code Hygiene (`RegisteredUserController.php`, `routes/web.php`) |

---

## 4. Detailed Findings

---

### SEC-01: Hardcoded Application Encryption Key Committed in `.env.example`
- **Severity:** Critical
- **Status:** Confirmed
- **Affected File & Lines:** `.env.example` (Line 3), `config/filesystems.php` (Lines 33–39), `vendor/laravel/framework/src/Illuminate/Filesystem/FilesystemServiceProvider.php` (Lines 98–113)
- **Description:**
  The project template `.env.example` contains a pre-generated, static Laravel encryption key:
  ```env
  APP_KEY=base64:KhF5W7wRFP7T7wh/ER+7LtJl98K/S20TQISc/+4XLt8=
  ```
  `composer.json` includes an automated post-root-package-install script:
  ```json
  "post-root-package-install": [
      "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
  ]
  ```
  If an environment is provisioned from `.env.example` and the developer or CI/CD pipeline does not run `php artisan key:generate`, the application operates with this publicly known key.
  
  In Laravel 12, `config/filesystems.php` configures the `local` disk with `'serve' => true`:
  ```php
  'local' => [
      'driver' => 'local',
      'root' => storage_path('app/private'),
      'serve' => true,
      'throw' => false,
      'report' => false,
  ],
  ```
  This automatically registers two un-prefixed, public routes:
  - `GET /storage/{path}` handled by `ServeFile`
  - `PUT /storage/{path}` handled by `ReceiveFile`
  Both routes rely on URL signature verification using HMAC-SHA256 keyed by `APP_KEY`.
- **Evidence from Code:**
  - `.env.example` (Line 3):
    ```env
    APP_KEY=base64:KhF5W7wRFP7T7wh/ER+7LtJl98K/S20TQISc/+4XLt8=
    ```
  - `config/filesystems.php` (Lines 33–37):
    ```php
    'local' => [
        'driver' => 'local',
        'root' => storage_path('app/private'),
        'serve' => true,
    ```
- **Realistic Attack Scenario:**
  1. An attacker discovers that the target site uses the default `APP_KEY` committed in `.env.example`.
  2. The attacker uses the known `APP_KEY` offline to generate a valid signed URL for `PUT /storage/backdoor.php?upload=1`.
  3. The attacker submits an HTTP `PUT` request with arbitrary file contents. Because the signature matches the known `APP_KEY`, `ReceiveFile` saves the payload into `storage/app/private/backdoor.php`.
  4. The attacker generates a signed URL for `GET /storage/backdoor.php` to access or download the file, or if the server configuration executes scripts in storage paths, achieves remote code execution.
  5. The attacker can additionally forge session cookies or decrypt encrypted cookies.
- **Security Impact:**
  Complete loss of system confidentiality and integrity. Enables arbitrary file upload and arbitrary file reading within private application storage, as well as session manipulation.
- **Recommended Remediation:**
  1. Immediately blank out `APP_KEY` in `.env.example`:
     ```env
     APP_KEY=
     ```
  2. Ensure `APP_KEY` is generated uniquely during setup via `php artisan key:generate`.
  3. Set `'serve' => false` in `config/filesystems.php` on the `local` private disk unless actively needed.
- **Regression Test to Add:**
  Create a test in `tests/Unit/ConfigurationSecurityTest.php` asserting that `.env.example` does not contain a populated `APP_KEY` and that `config('app.key')` does not match the committed default string.

---

### SEC-02: Plaintext Invitation Token Leakage into Application Logs
- **Severity:** High
- **Status:** Confirmed
- **Affected File & Lines:** `.env.example` (Line 53), `app/Http/Controllers/InvitationController.php` (Lines 53–67, 108–122)
- **Description:**
  The application generates a 64-character random token (`Str::random(64)`), hashes it with SHA-256 (`token_hash = hash('sha256', $plainToken)`), and stores only the hash in the database. This design is intended to prevent token exposure in the event of database dumps.
  
  However, `.env.example` sets:
  ```env
  MAIL_MAILER=log # using logs for invitation emails..
  ```
  In `InvitationController.php`, both `storeCompanyInvitation` and `storeCompanyMemberInvitation` build the full unhashed URL:
  ```php
  $invitationUrl = route('invitations.accept', ['token' => $plainToken]);
  ```
  And transmit it using `Mail::raw(...)`.
  When `MAIL_MAILER=log`, Laravel writes the entire raw email message—including `$invitationUrl` with the plaintext token—to `storage/logs/laravel.log`.
- **Evidence from Code:**
  - `.env.example` (Line 53):
    ```env
    MAIL_MAILER=log # using logs for invitation emails..
    ```
  - `app/Http/Controllers/InvitationController.php` (Lines 58–67):
    ```php
    Mail::raw(
        "You have been invited to administer {$company->name}.\n\n"
            . "Accept your invitation using this link:\n"
            . $invitationUrl
            . "\n\nThis link expires in 48 hours.",
        function ($message) use ($invitation) {
            $message->to($invitation->email)
                ->subject('Your company administrator invitation');
        }
    );
    ```
- **Realistic Attack Scenario:**
  In many staging, production, or shared environments, application logs are aggregated into log analyzers (Datadog, Papertrail, ELK) or accessible to read-only contractors, interns, or developers. An insider or an attacker who gains read access to `storage/logs/laravel.log` extracts the plaintext invitation URL before the legitimate recipient accepts it. The attacker visits `/invitations/{token}`, inputs their own name and password, and claims the newly created company's Admin account.
- **Security Impact:**
  Complete bypass of the invitation secrecy model. Unauthorized account takeover and privilege escalation to Company Admin or Member without possessing access to the recipient's mailbox.
- **Recommended Remediation:**
  1. In `.env.example`, change `MAIL_MAILER` to `smtp` or a safe development trap such as `fail` or documentation pointing to Mailpit/MailHog.
  2. Never use `MAIL_MAILER=log` in any shared or production environment when sending security tokens.
- **Regression Test to Add:**
  Create a test asserting that mail message generation in testing/production environments does not output plaintext invitation URLs to shared log files.

---

### SEC-03: User and Email Enumeration via Password Reset Endpoint
- **Severity:** Medium
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/Auth/PasswordResetLinkController.php` (Lines 40–43), `routes/auth.php` (Lines 46–48)
- **Description:**
  When a user submits the password reset request form (`POST /forgot-password`), the controller returns distinct messages based on whether the email address exists in the `users` table:
  ```php
  return $status == Password::RESET_LINK_SENT
      ? back()->with('status', __($status))
      : back()->withInput($request->only('email'))
          ->withErrors(['email' => __($status)]);
  ```
  If the email exists, the user receives: *"We have emailed your password reset link."*
  If the email does not exist, the user receives: *"We can't find a user with that email address."*
  
  Furthermore, `POST /forgot-password` in `routes/auth.php` does **not** have `throttle` middleware attached.
- **Evidence from Code:**
  - `app/Http/Controllers/Auth/PasswordResetLinkController.php` (Lines 40–44):
    ```php
    return $status == Password::RESET_LINK_SENT
                ? back()->with('status', __($status))
                : back()->withInput($request->only('email'))
                    ->withErrors(['email' => __($status)]);
    ```
  - `routes/auth.php` (Lines 46–48):
    ```php
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');
    ```
- **Realistic Attack Scenario:**
  An unauthenticated attacker runs an automated dictionary script against `/forgot-password` testing a list of employee email addresses. Because the route has no rate limiting and explicitly confirms invalid emails with a 422/validation error, the attacker compiles a complete roster of registered email addresses (Admins and Members) across all companies.
- **Security Impact:**
  Facilitates targeted phishing, credential stuffing, and email harvesting. Lack of rate limiting also allows an attacker to exhaust outbound mail quotas or flood recipient inboxes.
- **Recommended Remediation:**
  1. Return a uniform response regardless of whether the email exists:
     ```php
     return back()->with('status', 'If an account matches that email, a password reset link has been sent.');
     ```
  2. Add throttling middleware to the route in `routes/auth.php`:
     ```php
     Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
         ->middleware('throttle:5,1')
         ->name('password.email');
     ```
- **Regression Test to Add:**
  Create a test in `tests/Feature/Auth/PasswordResetTest.php` asserting that submitting both registered and non-registered emails returns an identical status message and status code, and that exceeding 5 attempts triggers HTTP 429.

---

### SEC-04: Missing Request Throttling on Short URL Creation and Public Redirects
- **Severity:** Medium
- **Status:** Confirmed
- **Affected File & Lines:** `routes/web.php` (Lines 81–83, 86–93), `app/Http/Controllers/ShortUrlController.php` (Lines 12–53, 55–62)
- **Description:**
  1. **Short URL Creation:** `POST /short-urls` has role checks (`role:admin,member`) but **no rate limiting**. Any authenticated Admin or Member can submit thousands of creation requests per minute.
  2. **Public Redirection & Click Tracking:** The public route `GET /{code}` has **no rate limiting**. On every single GET request, the controller performs a synchronous database write:
     ```php
     $shortUrl->increment('clicks');
     ```
     This translates to `UPDATE short_urls SET clicks = clicks + 1, updated_at = ... WHERE id = ...`.
- **Evidence from Code:**
  - `routes/web.php` (Lines 81–83, 91–93):
    ```php
    Route::get('/{code}', [ShortUrlController::class, 'redirect'])
        ->where('code', '[A-Za-z0-9]{8}')
        ->name('short-urls.redirect');

    Route::post('/short-urls', [ShortUrlController::class, 'store'])
        ->name('short-urls.store');
    ```
  - `app/Http/Controllers/ShortUrlController.php` (Lines 57–61):
    ```php
    $shortUrl = ShortUrl::where('code', $code)->firstOrFail();
    $shortUrl->increment('clicks');
    return redirect()->away($shortUrl->original_url);
    ```
- **Realistic Attack Scenario:**
  1. A malicious or compromised Member account runs a script creating 100,000 URLs, bloating table storage and indexes.
  2. An attacker or web crawler targets an existing short URL with a multi-threaded HTTP flood. Because each request locks the corresponding row in MySQL to increment the counter, the database connection pool is quickly saturated, triggering lock wait timeout exceptions (`Lock wait timeout exceeded`) and bringing down the entire web service for legitimate users.
  3. Clicks analytics are rendered meaningless by automated bots.
- **Security Impact:**
  Denial of Service (database saturation and row-lock contention), resource exhaustion, and compromise of click tracking integrity.
- **Recommended Remediation:**
  1. Apply `throttle:30,1` to `POST /short-urls`.
  2. Apply `throttle:120,1` to `GET /{code}`.
  3. Decouple click tracking from synchronous HTTP execution: queue the click increment via Redis (`Redis::incr`), a background job, or a buffered write mechanism to eliminate row locking on GET redirects.
- **Regression Test to Add:**
  Feature tests verifying that exceeding 30 creations per minute on `/short-urls` and 120 hits per minute on `/{code}` returns HTTP 429.

---

### SEC-05: Unhandled Foreign Key Constraint Violation on User Account Deletion
- **Severity:** Medium
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/ProfileController.php` (Lines 49–59), `database/migrations/2026_10_29_140907_create_short_urls_table.php` (Line 23), `database/migrations/2026_10_29_141409_create_invitations_table.php` (Line 23)
- **Description:**
  The database migrations define `restrictOnDelete()` on child tables referencing `users.id`:
  - `short_urls.user_id` -> `constrained('users')->restrictOnDelete()`
  - `invitations.invited_by` -> `constrained('users')->restrictOnDelete()`
  
  In `ProfileController::destroy`:
  ```php
  $user = $request->user();
  Auth::logout();
  $user->delete();
  $request->session()->invalidate();
  $request->session()->regenerateToken();
  ```
  `Auth::logout()` is called **before** `$user->delete()`. If the user has created any short URLs or sent any invitations, `$user->delete()` throws a `QueryException` (MySQL Error 1451: *Cannot delete or update a parent row: a foreign key constraint fails*).
- **Evidence from Code:**
  - `app/Http/Controllers/ProfileController.php` (Lines 51–53):
    ```php
    Auth::logout();
    $user->delete();
    ```
  - `database/migrations/2026_10_29_140907_create_short_urls_table.php` (Lines 21–23):
    ```php
    $table->foreignId('user_id')
        ->constrained('users')
        ->restrictOnDelete();
    ```
- **Realistic Attack Scenario:**
  A Member or Admin attempts to delete their account through the standard profile deletion form. The request fails with an unhandled 500 error. Because `Auth::logout()` already executed, the user is immediately logged out, but their user record still exists in the database. When the user attempts to log back in, their credentials still work. If `APP_DEBUG=true` is enabled, the error page displays the internal database schema, user IDs, and SQL constraints.
- **Security Impact:**
  Application crash (unhandled 500), corrupted application state (session invalidated while entity remains), and database information leakage in debug mode.
- **Recommended Remediation:**
  1. Check for dependent child records before attempting deletion or wrap deletion in a database transaction.
  2. Perform `Auth::logout()` **after** the deletion successfully completes.
  3. Define a clear business rule: either disallow deletion when active URLs exist (returning a friendly validation error) or reassign child records before deletion.
- **Regression Test to Add:**
  Create a test in `tests/Feature/ProfileTest.php` creating a user with an associated short URL and asserting that submitting the profile deletion form does not produce an uncaught database exception.

---

### SEC-06: Short Code Generation TOCTOU Race Condition on Collision
- **Severity:** Low
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/ShortUrlController.php` (Lines 29–45)
- **Description:**
  The short code generation routine checks for existence prior to saving:
  ```php
  do {
      $code = Str::random(8);
  } while (ShortUrl::where('code', $code)->exists());

  $shortUrl = new ShortUrl([...]);
  $shortUrl->company()->associate($user->company);
  $shortUrl->user()->associate($user);
  $shortUrl->save();
  ```
  This constitutes a Time-of-Check to Time-of-Use (TOCTOU) race condition. The check and insert are not wrapped in a database lock or retry block. If two concurrent requests generate the same random code, both pass the `exists()` check, but the second `$shortUrl->save()` will crash with a unique constraint violation on `short_urls.code`.
- **Evidence from Code:**
  - `app/Http/Controllers/ShortUrlController.php` (Lines 29–31):
    ```php
    do {
        $code = Str::random(8); // will generate a random 8-character string
    } while (ShortUrl::where('code', $code)->exists());
    ```
- **Realistic Attack Scenario:**
  Under high-throughput concurrent URL generation, two worker threads generate an identical 8-character string simultaneously. The second insert fails with an unhandled `QueryException`, causing an HTTP 500 error for the user.
- **Security Impact:**
  Intermittent unhandled 500 errors and potential database error leakage.
- **Recommended Remediation:**
  Wrap the insert in a retry loop catching `\Illuminate\Database\UniqueConstraintViolationException`:
  ```php
  $attempts = 0;
  do {
      try {
          $shortUrl = new ShortUrl(['original_url' => $validated['original_url'], 'code' => Str::random(8)]);
          $shortUrl->company()->associate($user->company);
          $shortUrl->user()->associate($user);
          $shortUrl->save();
          break;
      } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
          $attempts++;
          if ($attempts >= 5) throw $e;
      }
  } while (true);
  ```
- **Regression Test to Add:**
  Unit test asserting that catching a unique constraint collision triggers a retry rather than an uncaught exception.

---

### SEC-07: Permissive URL Scheme Validation Allowing Non-HTTP Protocols
- **Severity:** Low
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/ShortUrlController.php` (Lines 16–22)
- **Description:**
  The validation rule in `ShortUrlController::store` uses Laravel's generic `'url'` validator:
  ```php
  $validated = $request->validate([
      'original_url' => ['required', 'url', 'max:2048'],
  ]);
  ```
  By default, Laravel's `'url'` rule validates against PHP's `FILTER_VALIDATE_URL`. While `javascript:` and `data:` schemes are rejected by PHP, other protocol schemes such as `ftp://` (verified during runtime testing) are accepted.
- **Evidence from Code:**
  - `app/Http/Controllers/ShortUrlController.php` (Lines 16–22):
    ```php
    $validated = $request->validate([
        'original_url' => [
            'required',
            'url',
            'max:2048',
        ],
    ]);
    ```
  - Runtime verification: `filter_var('ftp://ftp.example.com', FILTER_VALIDATE_URL)` returns `true`.
- **Realistic Attack Scenario:**
  A Member creates a short link pointing to an external FTP server (`ftp://ftp.example.com/exploit`). When a victim clicks the link, the browser triggers an external protocol handler or legacy protocol connection, which may interact unexpectedly with local network devices.
- **Security Impact:**
  Acceptance of unintended network schemes; potential protocol smuggling or phishing vectors.
- **Recommended Remediation:**
  Restrict URL schemes explicitly to HTTP and HTTPS:
  ```php
  'original_url' => ['required', 'url:http,https', 'max:2048'],
  ```
- **Regression Test to Add:**
  Feature test in `tests/Feature/ShortUrlTest.php` submitting `ftp://example.com` and asserting a validation error for `original_url`.

---

### SEC-08: Missing Email Verification State on Invitation Acceptance
- **Severity:** Low
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/InvitationController.php` (Lines 178–185), `routes/web.php` (Line 19)
- **Description:**
  When an invited user completes the acceptance form (`InvitationController::accept`), the user record is created directly:
  ```php
  User::create([
      'name' => $validated['name'],
      'email' => $invitation->email,
      'password' => Hash::make($validated['password']),
      'company_id' => $invitation->company_id,
      'role' => $invitation->role,
  ]);
  ```
  `email_verified_at` is left `null`.
  
  In `routes/web.php`:
  ```php
  Route::get('/dashboard', function () {
      return view('dashboard');
  })->middleware(['auth', 'verified'])->name('dashboard');
  ```
  When the user logs in and attempts to view `/dashboard`, the `verified` middleware blocks access and redirects them to `/verify-email`. However, because `accept` did not trigger the `Registered` event, no verification notification was ever sent. Conversely, `/short-urls`, `/team`, and other core routes do **not** have the `verified` middleware, allowing the user to bypass the check entirely.
- **Evidence from Code:**
  - `app/Http/Controllers/InvitationController.php` (Lines 178–184) does not populate `email_verified_at`.
  - `routes/web.php` (Line 19) enforces `verified` only on `/dashboard`.
- **Realistic Attack Scenario:**
  A new company admin accepts their invitation, sets their credentials, and is redirected to the login page. Upon authenticating, they are immediately redirected away from the dashboard to an email verification prompt with no pending verification token in their inbox. If they guess or navigate directly to `/short-urls`, they bypass the prompt completely.
- **Security Impact:**
  Degraded authentication workflow and inconsistent security boundaries.
- **Recommended Remediation:**
  Because the user demonstrated possession of the email address by receiving and opening the invitation token, set `'email_verified_at' => now()` directly in `User::create` upon invitation acceptance.
- **Regression Test to Add:**
  Feature test verifying that an invited user can immediately access `/dashboard` upon login after accepting their invitation.

---

### SEC-09: Unbounded Invitation Creation & Missing Pending Uniqueness Constraint
- **Severity:** Low
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/InvitationController.php` (Lines 18–51), `database/migrations/2026_10_29_141409_create_invitations_table.php` (Line 34)
- **Description:**
  In `storeCompanyInvitation`, validation only checks `Rule::unique('users', 'email')`. It does **not** check whether a pending invitation already exists for that email.
  Furthermore, `storeCompanyInvitation` creates a `Company` record on each submission.
  If a SuperAdmin accidentally submits the form multiple times for the same email:
  1. Multiple company records are created.
  2. Multiple invitation records are created.
  3. When the user accepts the first invitation, their user account is created. All subsequent invitations for that email become permanently unacceptabler because `accept()` checks `User::where('email', $invitation->email)->exists()`.
  4. The remaining companies remain permanently orphaned in the database.
- **Evidence from Code:**
  - `app/Http/Controllers/InvitationController.php` (Lines 20–28):
    ```php
    $validated = $request->validate([
        'company_name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email'),
        ],
    ]);
    ```
- **Realistic Attack Scenario:**
  A SuperAdmin sends 10 company invitations to the same email address. 10 distinct companies are created in the database. Once the recipient activates the first link, the other 9 links become dead, leaving 9 inactive, orphaned companies with no users and un-cancellable orphaned records.
- **Security Impact:**
  Database pollution, orphaned tenant records, and poor state management.
- **Recommended Remediation:**
  Add a validation check in `storeCompanyInvitation`:
  ```php
  Rule::unique('invitations', 'email')
      ->whereNull('accepted_at')
      ->where('expires_at', '>', now()),
  ```
- **Regression Test to Add:**
  Feature test asserting that attempting to invite an email with an active pending company invitation fails validation.

---

### SEC-10: Inconsistent Password Complexity Requirements Across Workflows
- **Severity:** Low
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/InvitationController.php` (Line 155), `app/Http/Controllers/Auth/NewPasswordController.php` (Line 37), `app/Http/Controllers/Auth/PasswordController.php` (Line 20)
- **Description:**
  Password policies vary across different controllers in the application:
  - In `InvitationController::accept`: `'password' => ['required', 'string', 'min:12', 'confirmed']` (12 characters).
  - In `NewPasswordController::store`: `'password' => ['required', 'confirmed', Rules\Password::defaults()]` (8 characters default).
  - In `PasswordController::update`: `'password' => ['required', Password::defaults(), 'confirmed']` (8 characters default).
  A user required to select a strong 12-character password during onboarding can immediately downgrade their password to an 8-character weak password via the forgot-password flow or profile settings.
- **Evidence from Code:**
  - `InvitationController.php` (Line 155):
    ```php
    'password' => ['required', 'string', 'min:12', 'confirmed'],
    ```
  - `NewPasswordController.php` (Line 37):
    ```php
    'password' => ['required', 'confirmed', Rules\Password::defaults()],
    ```
- **Realistic Attack Scenario:**
  An organization mandates a 12-character minimum password policy. An onboarded user sets a 12-character password, immediately triggers `/forgot-password`, and resets their password to an 8-character string, circumventing the intended baseline complexity.
- **Security Impact:**
  Inconsistent password policy enforcement and credential weakness.
- **Recommended Remediation:**
  Define the global default in `AppServiceProvider::boot()`:
  ```php
  Password::defaults(function () {
      return Password::min(12)->letters()->numbers()->symbols();
  });
  ```
  Reference `Password::defaults()` consistently in `InvitationController`, `NewPasswordController`, and `PasswordController`.
- **Regression Test to Add:**
  Feature test verifying that resetting a password with fewer than 12 characters is rejected.

---

### SEC-11: Missing Browser Security Headers and Reverse Tabnabbing Weakness
- **Severity:** Low
- **Status:** Confirmed
- **Affected File & Lines:** `resources/views/short-urls/index.blade.php` (Lines 26–27), `bootstrap/app.php`
- **Description:**
  1. **Reverse Tabnabbing:** In `short-urls/index.blade.php`, the short code link is rendered as:
     ```blade
     <a href="{{ route('short-urls.redirect', ['code' => $shortUrl->code]) }}" target="_blank">
     ```
     Opening external links in a new window without `rel="noopener noreferrer"` allows the destination site partial access to the opener window's `window.opener` object in older browsers.
  2. **Security Headers:** The application does not register middleware to inject standard defense-in-depth security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, or `Content-Security-Policy`).
- **Evidence from Code:**
  - `resources/views/short-urls/index.blade.php` (Lines 26–29):
    ```blade
    <a href="{{ route('short-urls.redirect', ['code' => $shortUrl->code]) }}"
       target="_blank">
        {{ $shortUrl->code }}
    </a>
    ```
- **Realistic Attack Scenario:**
  An attacker embeds the URL shortener within an iframe on a malicious webpage to execute clickjacking attacks against authenticated Admins (e.g. clicking "Cancel Invitation" or "Logout"). On older browsers, a redirected destination page could use `window.opener.location` to navigate the original tab to a phishing clone.
- **Security Impact:**
  Clickjacking vulnerability and minor cross-window navigation risks.
- **Recommended Remediation:**
  1. Add `rel="noopener noreferrer"` to all links containing `target="_blank"`.
  2. Configure standard security headers in `bootstrap/app.php` or a dedicated middleware (`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`).
- **Regression Test to Add:**
  HTTP test verifying that responses include `X-Frame-Options` and `X-Content-Type-Options` headers.

---

### SEC-12: Default SuperAdmin Seed Credentials and Fragile Deletion State
- **Severity:** Informational
- **Status:** Confirmed
- **Affected File & Lines:** `database/seeders/SuperAdminSeeder.php` (Lines 14–25), `.env.example` (Lines 14–15), `app/Http/Controllers/ProfileController.php` (Lines 49–59)
- **Description:**
  1. `.env.example` provides default values:
     ```env
     SUPERADMIN_EMAIL=email@superadmin.com
     SUPERADMIN_PASSWORD=password
     ```
     `SuperAdminSeeder` reads these environment variables. If a fresh deployment seeds the database using default environment settings, the SuperAdmin account is configured with the trivial password `password`.
  2. If the SuperAdmin logs in and deletes their profile (`DELETE /profile`) before creating any invitations or companies (when no foreign key restrictions exist), the account is permanently deleted. There is no workflow to invite or provision another SuperAdmin, leaving the application without administrative governance.
- **Evidence from Code:**
  - `.env.example` (Lines 14–15):
    ```env
    SUPERADMIN_EMAIL=email@superadmin.com
    SUPERADMIN_PASSWORD=password
    ```
  - `database/seeders/SuperAdminSeeder.php` (Lines 14–25).
- **Realistic Attack Scenario:**
  A system is deployed to a public staging server without modifying default `.env` credentials. An attacker logs in with `email@superadmin.com` and `password`, obtaining full global read access across all company URLs and creating arbitrary rogue companies.
- **Security Impact:**
  Exposure of default administrative credentials; potential loss of global administrative capabilities if deleted.
- **Recommended Remediation:**
  1. Require strong, randomly generated passwords during initial setup.
  2. Add a policy or gate in `ProfileController::destroy` preventing users with the `super_admin` role from deleting their own account.
- **Regression Test to Add:**
  Test asserting that a user with `role === 'super_admin'` cannot execute account deletion via `/profile`.

---

### SEC-13: Orphaned Laravel Breeze Scaffolding and Incomplete Route Artifacts
- **Severity:** Informational
- **Status:** Confirmed
- **Affected File & Lines:** `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/views/auth/register.blade.php`, `resources/views/layouts/navigation.blade.php`, `routes/web.php` (Lines 9–11, 37–43, 60–62, 75–78)
- **Description:**
  The repository retains leftover, unreferenced files from Laravel Breeze:
  - `RegisteredUserController.php` and `register.blade.php` remain in the codebase despite the registration route being commented out.
  - `resources/views/layouts/navigation.blade.php` is Breeze's navigation template, but `layouts/app.blade.php` defines an independent layout and does not include it.
  - `routes/web.php` contains commented-out code blocks and a leftover debug route:
    ```php
    Route::get('/admin-area', function () {
        return 'Admin access granted';
    });
    ```
- **Evidence from Code:**
  - `routes/web.php` (Lines 60–62): Placeholder test route active under `role:admin`.
- **Security Impact:**
  Code hygiene and maintenance risk. If an engineer mistakenly uncomments `/register` in `routes/auth.php`, public self-registration without company association will be immediately exposed.
- **Recommended Remediation:**
  Remove unused Breeze controllers, redundant views, commented-out dead route blocks, and placeholder test routes.
- **Regression Test to Add:**
  Code review and static analysis hygiene checks.

---

## 5. Authorization Matrix

The table below documents expected system behavior versus observed implementation in source code.

| Capability / Action | SuperAdmin Expected | SuperAdmin Observed | Admin Expected | Admin Observed | Member Expected | Member Observed | Verification Notes |
|---|---|---|---|---|---|---|---|
| **Create Company** | Allowed | **Allowed** | Denied | **Denied** (403) | Denied | **Denied** (403) | Route guarded by `role:super_admin`. |
| **Invite First Company Admin** | Allowed | **Allowed** | Denied | **Denied** (403) | Denied | **Denied** (403) | Route guarded by `role:super_admin`. |
| **Invite Admin / Member to Own Company** | Denied | **Denied** (403) | Allowed | **Allowed** | Denied | **Denied** (403) | Guarded by `role:admin`. |
| **Invite User to Another Company** | Denied | **Denied** | Denied | **Denied** | Denied | **Denied** | Admin forced to `$user->company_id`. |
| **Create Short URL** | **Denied** | **Denied** (403) | Allowed | **Allowed** | Allowed | **Allowed** | Guarded by `role:admin,member` & `!$user->company_id` aborts 403. |
| **List All Short URLs (Cross-Company)** | Allowed | **Allowed** | Denied | **Denied** | Denied | **Denied** | SuperAdmin branches to all URLs; Admin filtered by `company_id`. |
| **List Own Company Short URLs** | Allowed | **Allowed** | Allowed | **Allowed** | Denied | **Denied** | Admin filtered by `company_id`; Member filtered by `user_id`. |
| **List Own Short URLs** | Allowed | **Allowed** | Allowed | **Allowed** | Allowed | **Allowed** | Members see only `user_id == $user->id`. |
| **View Individual Short URL by ID** | N/A | **N/A** | N/A | **N/A** | N/A | **N/A** | No show/edit routes exist in the application. |
| **Delete / Edit Short URL** | N/A | **N/A** | N/A | **N/A** | N/A | **N/A** | No update or delete endpoints exist. |
| **View Companies / Team List** | Allowed (All) | **Allowed** (All) | Allowed (Own) | **Allowed** (Own) | Denied | **Denied** (403) | Guarded by `role:super_admin,admin`. |
| **List Pending Invitations** | Allowed (All) | **Allowed** (All) | Allowed (Own) | **Allowed** (Own) | Denied | **Denied** (403) | Admin scoped to `$user->company_id`. |
| **Cancel Own Company Invitation** | Allowed | **Allowed** | Allowed | **Allowed** | Denied | **Denied** (403) | Admin checked against `$invitation->company_id`. |
| **Cancel Other Company Invitation** | Allowed | **Allowed** | Denied | **Denied** (403) | Denied | **Denied** (403) | Admin aborted 403 if `company_id` does not match. |
| **Public Short URL Redirection** | Allowed | **Allowed** | Allowed | **Allowed** | Allowed | **Allowed** | Unauthenticated public access. |
| **Role Escalation via Profile Update** | Denied | **Denied** | Denied | **Denied** | Denied | **Denied** | `ProfileUpdateRequest` validates only `name` and `email`. |

---

## 6. Invitation Security Review

### 6.1 Token Generation and Entropy
- In `InvitationController::storeCompanyInvitation` and `storeCompanyMemberInvitation`:
  ```php
  $plainToken = Str::random(64);
  ```
  `Str::random` uses PHP's cryptographically secure pseudo-random number generator (`random_bytes()`), providing approximately 380 bits of entropy. The token is unpredictable and resistant to brute-force or guessing attacks.

### 6.2 Token Storage and Hashing
- Tokens are stored in the database exclusively as SHA-256 hashes:
  ```php
  'token_hash' => hash('sha256', $plainToken)
  ```
  The plaintext token is never persisted to the database. Even in the event of a database compromise or backup theft, invitation tokens cannot be reversed.
  *(Note: This security measure is partially undermined by `MAIL_MAILER=log`, as detailed in SEC-02).*

### 6.3 Expiration Enforcement
- Invitations are created with a 48-hour lifespan:
  ```php
  'expires_at' => now()->addHours(48)
  ```
- Expiration is strictly enforced in:
  - `showAcceptanceForm`: `if ($invitation->expires_at->isPast()) abort(404);`
  - `accept`: Checked inside the database lock before account creation.
  - `cancel`: Only unexpired, pending invitations can be cancelled.

### 6.4 Single-Use Enforcement and Race Conditions
- In `InvitationController::accept`:
  ```php
  DB::transaction(function () use ($token, $validated) {
      $invitation = Invitation::where(
          'token_hash',
          hash('sha256', $token)
      )->lockForUpdate()->first();

      if (!$invitation || $invitation->accepted_at || $invitation->expires_at->isPast()) {
          abort(404, 'This invitation is invalid, expired, or already used.');
      }
      ...
      $invitation->update(['accepted_at' => now()]);
  });
  ```
  Pessimistic row locking (`lockForUpdate()`) ensures that concurrent acceptance requests cannot execute simultaneous `User::create` operations. The first transaction marks `accepted_at`, causing subsequent transactions to abort with 404.

### 6.5 Cancellation Authorization
- `DELETE /invitations/{id}` verifies permissions server-side:
  - SuperAdmin can cancel any pending invitation.
  - Admin is restricted: `abort_unless($user->company_id && $invitation->company_id === $user->company_id, 403);`.
  - Members are blocked by middleware (`role:super_admin,admin`).

### 6.6 Intended Recipient Verification
- The invitation acceptance form binds the account to the email, role, and company specified in the invitation record. Users cannot alter their assigned role or company during acceptance.
- However, the system acts on a **bearer token** model: anyone possessing the URL can provide an arbitrary name and password. If the link is leaked or intercepted, the bearer becomes the user.

---

## 7. URL Security Review

### 7.1 Validation Rules
- `ShortUrlController::store` enforces:
  - `required`
  - `url` (validates standard URLs, but permits non-HTTP schemes such as `ftp://`, see SEC-07)
  - `max:2048` (matches the database schema `VARCHAR(2048)` and protects against buffer/truncation attacks)

### 7.2 Short Code Generation
- Generates 8-character random alphanumeric strings using `Str::random(8)`.
- Key space is $62^8 \approx 2.18 \times 10^{14}$ possibilities.
- While collisions are improbable initially, the TOCTOU check without a retry handler exposes the system to unhandled 500 exceptions under high load (see SEC-06).

### 7.3 Redirection Mechanics and Open Redirect Analysis
- Public route: `GET /{code}` constrained by `where('code', '[A-Za-z0-9]{8}')`.
- Redirect execution: `return redirect()->away($shortUrl->original_url);`.
- **Open Redirect Classification:** The purpose of a URL shortener is to redirect visitors to an arbitrary destination URL created by authorized users. This is an intended feature and **not** an open redirect vulnerability. The risk is limited to link abuse/phishing, which can be mitigated by scheme restriction (`url:http,https`) and abuse reporting mechanisms.

### 7.4 Click Tracking and Concurrency Bottleneck
- On every redirect, `$shortUrl->increment('clicks')` executes synchronously against MySQL.
- This creates an immediate write-lock bottleneck on the database row. A sudden surge in traffic to a single short link will degrade overall application responsiveness (see SEC-04).

---

## 8. Authentication, Database, and Configuration Review

### 8.1 Code-Level vs. Environment-Dependent Checks

| Check Item | Implementation Finding | Environment / Deployment Dependency |
|---|---|---|
| **Session Regeneration** | Properly invoked in `AuthenticatedSessionController::store`. | Fully enforced at code level. |
| **Session Invalidation** | Properly invoked in `AuthenticatedSessionController::destroy`. | Fully enforced at code level. |
| **Session Cookie Security** | `config/session.php` reads `env('SESSION_SECURE_COOKIE')`. | **Environment dependent.** If HTTPS is enabled but `SESSION_SECURE_COOKIE` is not set to `true`, cookies may transit over insecure HTTP. |
| **HttpOnly Flag** | `config/session.php` sets `'http_only' => true`. | Enforced by default configuration. |
| **SameSite Cookie** | `config/session.php` sets `'same_site' => 'lax'`. | Enforced by default configuration. |
| **Debug Mode** | `config/app.php` sets `'debug' => env('APP_DEBUG', false)`. | **Environment dependent.** `.env.example` has `APP_DEBUG=true`. If deployed with debug active, database errors expose full stack traces. |
| **Database Encryption** | Passwords hashed using bcrypt (default factor 12). | Enforced via `Hash::make()` and Eloquent `'hashed'` cast. |
| **SQL Injection** | Parameterized queries used in `SuperAdminSeeder` and Eloquent queries. | Protected against SQL injection at code level. |
| **Mass Assignment** | Model `$fillable` attributes are appropriately restricted. Form requests strictly limit allowed inputs. | Protected at code level. |

---

## 9. Test Coverage Gaps

Existing test suites were thoroughly inspected in `tests/Feature/` and `tests/Unit/`. The automated tests in place verify basic URL creation for Admins and Members, SuperAdmin URL creation denial, cross-company URL visibility, and basic Breeze authentication/profile flows.

### Critical Missing Security Tests

1. **Invitation Workflow Tests (Zero Coverage):**
   - No test verifying SuperAdmin company creation and admin invitation (`POST /companies/invite-admin`).
   - No test verifying Company Admin inviting a member or admin (`POST /company/invitations`).
   - No test verifying that a Member or SuperAdmin is forbidden from inviting company members.
   - No test verifying successful invitation acceptance via `POST /invitations/{token}`.
   - No test asserting that expired invitations (>48 hours) cannot be accepted.
   - No test asserting that an accepted invitation cannot be reused.
   - No test asserting that role escalation attempts in member invitation (e.g. submitting `role=super_admin`) are rejected.
2. **Invitation Cancellation Security:**
   - No test asserting that an Admin cannot cancel an invitation belonging to another company.
   - No test asserting that a Member cannot cancel invitations.
3. **Team Management Authorization:**
   - No test verifying that Members receive 403 Forbidden on `GET /team`.
   - No test verifying that Company Admins only see their own company's members on `GET /team`.
4. **URL Scheme and Rate Limiting:**
   - No test verifying that unsupported schemes (e.g. `ftp://`, `javascript:`) are rejected.
   - No test verifying rate limiting on `/short-urls` or `/{code}`.
5. **Account Deletion Edge Cases:**
   - Existing profile test (`ProfileTest::test_user_can_delete_their_account`) tests only a user with no short URLs or invitations. No test verifies what happens when an active user attempts account deletion.

---

## 10. Remediation Plan

### Immediate Priority (Critical & High Risks)
1. **Remove Hardcoded `APP_KEY` from `.env.example` (SEC-01):**
   - Blank out `APP_KEY=` in `.env.example`.
   - Ensure production deployment checklists mandate `php artisan key:generate`.
   - Disable `'serve' => false` on the `local` disk in `config/filesystems.php`.
2. **Eliminate Plaintext Token Logging (SEC-02):**
   - In `.env.example`, change `MAIL_MAILER=log` to `smtp` or a dedicated local mail trap (e.g. Mailpit).
   - Never write security-sensitive acceptance URLs to persistent logs.

### Next Priority (Medium Risks)
3. **Prevent Email Enumeration on Password Reset (SEC-03):**
   - Standardize responses in `PasswordResetLinkController::store` so that existing and non-existing email submissions return the exact same user-facing message.
   - Add `middleware('throttle:5,1')` to `POST /forgot-password` in `routes/auth.php`.
4. **Implement Throttling & Optimize Click Tracking (SEC-04):**
   - Attach `throttle:30,1` to `POST /short-urls`.
   - Attach `throttle:120,1` to `GET /{code}`.
   - Transition click incrementing from synchronous SQL updates to an asynchronous job or Redis counter.
5. **Handle Foreign Key Constraints on Account Deletion (SEC-05):**
   - In `ProfileController::destroy`, verify whether the user owns short URLs or invitations before calling `Auth::logout()`.
   - Either disallow deletion with a clean validation error or reassign/delete dependent records inside a database transaction.

### Later Priority (Low Risks & Hardening)
6. **Enforce HTTP/HTTPS URL Schemes (SEC-07):**
   - Update `ShortUrlController::store` validation to `'url:http,https'`.
7. **Safeguard Short Code Collisions (SEC-06):**
   - Wrap short URL persistence in a `try/catch` catching `UniqueConstraintViolationException` with a retry count.
8. **Normalize Email Verification on Invitation Acceptance (SEC-08):**
   - Explicitly populate `'email_verified_at' => now()` when creating a user in `InvitationController::accept`.
9. **Synchronize Password Complexity Rules (SEC-10):**
   - Centralize password rules using `Password::defaults()` in `AppServiceProvider::boot()` and enforce a consistent 12-character minimum across all entry points.
10. **Add Security Headers and Reverse Tabnabbing Protection (SEC-11):**
    - Add `rel="noopener noreferrer"` to `short-urls/index.blade.php`.
    - Register standard security headers (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) in middleware.
11. **Clean Up Dead Breeze Scaffolding (SEC-13):**
    - Remove orphaned `RegisteredUserController.php`, unused views, and placeholder routes.

---

## 11. Verification Record

### Components and Files Inspected
- **Routing:** `routes/web.php`, `routes/auth.php`, `routes/console.php`
- **Controllers:**
  - `app/Http/Controllers/ShortUrlController.php`
  - `app/Http/Controllers/InvitationController.php`
  - `app/Http/Controllers/TeamController.php`
  - `app/Http/Controllers/ProfileController.php`
  - `app/Http/Controllers/Auth/*` (all Breeze controllers)
- **Middleware:** `app/Http/Middleware/RoleMiddleware.php`, `bootstrap/app.php`
- **Models & Requests:**
  - `app/Models/User.php`
  - `app/Models/Company.php`
  - `app/Models/ShortUrl.php`
  - `app/Models/Invitation.php`
  - `app/Http/Requests/ProfileUpdateRequest.php`
  - `app/Http/Requests/Auth/LoginRequest.php`
- **Database & Migrations:**
  - `database/migrations/*` (all 7 migration files)
  - `database/seeders/DatabaseSeeder.php`
  - `database/seeders/SuperAdminSeeder.php`
- **Views:**
  - `resources/views/layouts/app.blade.php`
  - `resources/views/layouts/navigation.blade.php`
  - `resources/views/short-urls/*`
  - `resources/views/invitations/*`
  - `resources/views/team/*`
- **Configuration & Dependencies:**
  - `config/app.php`, `config/auth.php`, `config/session.php`, `config/filesystems.php`, `config/database.php`
  - `.env.example`, `.gitignore`, `composer.json`, `composer.lock`
- **Test Suites:**
  - `tests/Feature/ShortUrlTest.php`
  - `tests/Feature/ProfileTest.php`
  - `tests/Feature/Auth/*`
  - `phpunit.xml`

### Safe, Read-Only Checks Executed
1. `php artisan route:list`: Verified all 35 registered routes, HTTP verbs, middleware chains, and handler closures.
2. `php artisan route:list --path=storage --json`: Uncovered active `storage.local` and `storage.local.upload` routes lacking middleware.
3. `php -m | Select-String "sqlite"` & PDO driver query: Confirmed that PHP has only the `pdo_mysql` driver enabled (no `pdo_sqlite`).
4. PHP runtime URL validation probe: Confirmed that `FILTER_VALIDATE_URL` accepts `ftp://` but rejects `javascript:`.
5. Blade view scan: Verified zero unescaped `{!! !!}` expressions across all templates.

### Tests Not Performed & Justification
- **Automated Test Suite (`php artisan test`):**
  Execution of `php artisan test` was deliberately omitted. In `phpunit.xml`, the in-memory SQLite configuration is commented out (`<!-- <env name="DB_CONNECTION" value="sqlite"/> -->`). Furthermore, the runtime environment does not have the SQLite extension loaded. Executing the test suite would cause `RefreshDatabase` to run `migrate:fresh` against the active MySQL database, altering or destroying project state in direct violation of the audit-only mandate.

---

## 12. Limitations

1. **Static Analysis Boundary:** This security review was conducted via static source code examination and non-destructive runtime inspection. It does not replace dynamic application security testing (DAST) or hands-on penetration testing.
2. **Infrastructure and Host Environment:** Production server configuration, reverse proxies (Nginx/Apache), TLS certificates, firewall/WAF rules, database access controls, and DNS records were outside the scope of this repository inspection.
3. **Dependency Vulnerability Database:** Static version review was performed against `composer.json` and `composer.lock`. Comprehensive software composition analysis (SCA) against external vulnerability feeds (CVE/Advisory databases) was limited by read-only offline boundaries.

