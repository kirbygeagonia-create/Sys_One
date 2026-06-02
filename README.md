# SkillLoop

**Trade Skills, Not Money.**

A peer-to-peer skill barter platform where students teach each other using a credit-based economy. Built as a systems project for SEAIT (Systems and Engineering Academe Information Technology).

## Features

### Core System
- **Credit Economy** — New users get 3 free credits. Teaching earns credits, learning spends them. No direct swaps needed — the system balances itself.
- **Dual-Confirmation Sessions** — Both teacher and learner must confirm a session is complete before credits transfer. Prevents disputes.
- **Skill Badges** — Teachers issue beginner/intermediate/advanced badges after completed sessions. Build your credential portfolio.
- **Reputation System** — Learners rate sessions (1–5 stars). Teacher reputation is averaged from all reviews.
- **Notification System** — Real-time bell indicator with read/unread tracking for all events (requests, acceptances, completions, badges).

### Security
- **CSRF Protection** — Every POST form validates a 64-character hex token via `hash_equals()`.
- **Rate-Limited Login** — 5 failed attempts triggers a 15-minute lockout.
- **XSS Prevention** — All user output escaped through `htmlspecialchars()` (`h()` helper).
- **SQL Injection Prevention** — All database queries use PDO prepared statements with no raw string interpolation.
- **Password Security** — Bcrypt hashing via `password_hash(PASSWORD_DEFAULT)`.
- **File Upload Safety** — MIME type whitelist (JPG, PNG, GIF, WebP), 2MB size limit.
- **Authorization Checks** — Every action verifies session ownership and role (teacher vs requester).

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.2 |
| Database | MySQL 8 with PDO |
| Frontend | Vanilla CSS, JavaScript |
| Icons | Font Awesome 6.5.1 (CDN) |
| Font | Poppins (Google Fonts) |
| Auth | Session-based with CSRF tokens |

## Installation

### Requirements
- PHP 8.0+
- MySQL 8.0+
- Web browser

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/kirbygeagonia-create/Sys_One.git
   cd Sys_One
   ```

2. **Configure the database**
   Edit `config/database.php` or set environment variables:
   ```php
   $host = getenv('DB_HOST') ?: 'localhost';
   $dbname = getenv('DB_NAME') ?: 'skillloop';
   $username = getenv('DB_USER') ?: 'root';
   $password = getenv('DB_PASS') ?: '';
   ```

3. **Initialize the database**
   ```bash
   mysql -u root < sql/schema.sql
   ```
   This creates the database, all tables, and seeds 10 skill categories with 47 skills.

4. **Start the development server**
   ```bash
   php -S localhost:8000
   ```
   **Do not** use `index.php` as a router script — serve files from the root directly.

5. **Visit the application**
   Open `http://localhost:8000` in your browser.

## Database Schema

14 tables with foreign key relationships and `ON DELETE CASCADE`:

- `users` — Core user accounts (name, email, password, credits, reputation, avatar)
- `skill_categories` — 10 categories (Music, Technology, Cooking, etc.) with Font Awesome icons
- `skills` — 47 skills linked to categories
- `user_skills_offered` — Skills a user teaches (with proficiency level)
- `user_skills_wanted` — Skills a user wants to learn
- `session_requests` — Initial learning requests (pending/accepted/declined)
- `sessions` — Confirmed sessions with scheduling and dual-confirmation tracking
- `session_reviews` — Ratings and comments (UNIQUE per session + reviewer)
- `badges` — Skill badges issued by teachers (beginner/intermediate/advanced)
- `credit_transactions` — Full audit log of all credit movements
- `notifications` — User notifications with read/unread tracking
- `password_reset_tokens` — Secure password reset with 1-hour expiry

## File Structure

```
├── index.php                 # Landing page with login/register modals
├── install.php               # Database installer script
├── migrate_icons.php         # Icon migration utility
├── .gitattributes            # Git config (linguist overrides)
├── config/
│   └── database.php          # PDO connection (env-var configurable)
├── includes/
│   ├── functions.php         # 25+ helper functions (auth, CSRF, credits, notifications)
│   ├── header.php            # HTML head, navbar, flash messages
│   └── footer.php            # Footer, JS scripts
├── auth/
│   ├── login.php             # Rate-limited login (5 attempts → 15-min lockout)
│   ├── register.php          # Registration with credit bonus
│   ├── logout.php            # Session destroy
│   ├── forgot_password.php   # Password reset request
│   └── reset_password.php    # Token-based password reset
├── pages/
│   ├── dashboard.php         # User dashboard with stats
│   ├── browse.php            # Browse/search teachers
│   ├── skills.php            # Manage offered/wanted skills
│   ├── sessions.php          # View and manage sessions
│   ├── profile.php           # User profile with badges
│   ├── credits.php           # Credit transaction history
│   └── notifications.php     # Notification center
├── actions/
│   ├── add_skill.php         # Add/remove offered or wanted skills
│   ├── get_skills.php        # AJAX endpoint for skills by category
│   ├── request_session.php   # Request a learning session
│   ├── respond_request.php   # Accept or decline a session request
│   ├── complete_session.php  # Dual-confirmation session completion
│   ├── cancel_session.php    # Cancel a scheduled session
│   ├── submit_review.php     # Rate session and issue badges
│   ├── upload_avatar.php     # Profile photo upload
│   ├── mark_read.php         # Mark single notification as read
│   └── mark_all_read.php     # Mark all notifications as read
├── assets/
│   ├── css/style.css         # All stylesheets (no inline CSS)
│   └── js/script.js          # All JavaScript (nav, modals, toasts, password strength)
├── sql/
│   └── schema.sql            # Full schema + seed data
└── tests/
    ├── test_suite.php        # 138 automated tests
    └── e2e_flow.php          # 33 end-to-end flow tests
```

## Testing

Run the full test suite from the command line:

```bash
php tests/test_suite.php    # 138 tests — syntax, security, DB, CSS, JS, emoji enforcement
php tests/e2e_flow.php      # 33 tests — full business flow end-to-end
```

### What the tests cover

**Test Suite (138 tests):**
- PHP environment checks (version, extensions)
- File structure verification (all required files exist)
- PHP syntax validation (every file parsed by `php -l`)
- Schema table existence (all 14 tables)
- Function availability (all 25+ helper functions)
- CSRF token generation and validation
- Flash message roundtrip
- HTML escaping correctness
- CSS file completeness (responsive, modals, notifications, etc.)
- JavaScript function presence
- **Inline CSS enforcement** — scans all PHP files for `style=""` attributes (zero-tolerance)
- **Emoji enforcement** — scans all source files for emoji Unicode ranges (use Font Awesome instead)
- Database connection and seed data verification

**E2E Flow (33 tests):**
- User registration with welcome credits
- Skill offer/want management with duplicate prevention
- Session request, acceptance, and scheduling
- Dual-confirmation completion with credit transfer
- Review submission with reputation recalculation
- Badge issuance with notification
- Read/unread notification tracking
- Credit transaction audit log
- Session cancellation
- Edge cases (duplicate email, invalid IDs, CSRF validation, timestamps)

## User Flows

### Registration & Onboarding
1. New user registers → receives 3 welcome credits
2. Adds skills they can teach (offer) and skills they want to learn
3. Dashboard shows stats, recent sessions, badges, and activity

### Session Lifecycle
1. **Request** — Learner browses teachers, sends request with message and scheduled time
2. **Accept** — Teacher accepts (or declines) the request
3. **Complete** — Both parties independently mark the session as complete
4. **Review** — Both parties rate the session; teacher can issue a skill badge
5. **Credits** — 1 credit transfers from learner to teacher upon dual confirmation

### Credit Economy
- **Earn** — Teach a session (1 credit), welcome bonus (3 credits)
- **Spend** — Learn a session (1 credit)
- **Bonus** — System grants (e.g., welcome bonus)
- All transactions are logged with counterparty, reference type, and description

## Theming

The UI is styled to match the SEAIT campus identity:
- **Primary:** `#FF6B35` (SEAIT orange)
- **Dark:** `#1A2332` (refined navy)
- **Background:** `#F8F6F3` (warm light)
- **Font:** Poppins (Google Fonts)

## Design Decisions

- **No JavaScript framework** — Pure vanilla JS keeps dependencies at zero and load times minimal.
- **No inline CSS** — All styles go through `style.css` with CSS custom properties. Enforced by automated tests.
- **No emoji** — All icons use Font Awesome for consistent rendering across platforms. Enforced by automated tests.
- **Dual confirmation** — Prevents credit disputes by requiring both parties to agree the session happened.
- **Session-based auth** — Simple, stateless on the server side (no JWT complexity needed for a campus platform).
- **Credit floor** — Users cannot spend credits they don't have (checked at session request time).

## License

Built for educational purposes as part of a systems project at SEAIT.