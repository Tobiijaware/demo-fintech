# Demo-backend — local setup

Laravel 12 API for the agency banking demo. All secrets and connection details go in **one file** at the project root:

```
Demo-backend/.env
```

That file is **not** committed to git (see `.gitignore`). You create it by copying the template.

---

## 1. Prerequisites

Install on your Mac:

| Tool | Version | Install |
|------|---------|---------|
| **PHP** | 8.2+ | `brew install php` |
| **Composer** | 2.x | `brew install composer` |
| **MySQL client** (optional) | — | `brew install mysql-client` — only if you want `mysql` CLI |

Check:

```bash
php -v          # should be 8.2+
composer -V
```

**Alternative (no local PHP):** use Docker — see [§7 Docker](#7-docker-optional) below.

---

## 2. Create `.env` from the template

From the backend folder:

```bash
cd /Users/admin/Documents/Projects/Demo-backend
cp .env.example .env
```

Open `.env` in your editor and fill in the values from your team (connection string, API keys, etc.).

---

## 3. Where each value goes

### App

| Variable | What to set | Example |
|----------|-------------|---------|
| `APP_NAME` | Display name | `iWallet API` |
| `APP_ENV` | `local` for development | `local` |
| `APP_DEBUG` | `true` locally | `true` |
| `APP_URL` | URL you open in the browser | `http://localhost:8000` |
| `APP_KEY` | Leave empty first; generate in step 5 | *(generated)* |

### Database (remote / online DB)

You can use **either** a single URL **or** separate fields.

**Option A — full connection string (easiest if you already have one)**

If your provider gives something like:

`mysql://USER:PASSWORD@HOST:3306/DATABASE`

put it in:

```env
DB_CONNECTION=mysql
DB_URL=mysql://USER:PASSWORD@HOST:3306/DATABASE
```

Laravel reads `DB_URL` and fills host, port, database, user, and password for you.

**Option B — separate fields**

```env
DB_CONNECTION=mysql
DB_HOST=your-db-host.rds.amazonaws.com
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_user
DB_PASSWORD=your_password
```

**SSL (common for AWS RDS / managed MySQL)**

This repo already ships an RDS CA bundle at `storage/certs/global-bundle.pem`. For remote MySQL with SSL:

```env
MYSQL_ATTR_SSL_CA=storage/certs/global-bundle.pem
MYSQL_SSL_VERIFY_SERVER_CERT=true
```

If you get SSL errors during local dev only, you can temporarily set:

```env
MYSQL_SSL_VERIFY_SERVER_CERT=false
```

*(Use `true` in production.)*

### JWT (required for login / API auth)

```env
JWT_SECRET=
```

Generate after `composer install`:

```bash
php artisan jwt:secret
```

That writes `JWT_SECRET` into `.env` automatically.

### Registration demo OTP (no real email needed for demo)

```env
REGISTRATION_USE_DEMO_OTP=true
REGISTRATION_DEMO_OTP_CODE=000000
```

With this on, email verification always accepts `000000`.

### Dojah (BVN/NIN — optional for local)

Only needed if you test real KYC calls:

```env
DOJAH_BASE_URL=https://api.dojah.io
DOJAH_APP_ID=your_app_id
DOJAH_SECRET_KEY=your_secret
```

### Mail (optional locally)

Default `MAIL_MAILER=log` writes emails to `storage/logs/laravel.log` — fine for demo.

---

## 4. Install dependencies

```bash
cd /Users/admin/Documents/Projects/Demo-backend
composer install
```

---

## 5. Generate keys & prepare database

```bash
# Application encryption key (required)
php artisan key:generate

# JWT signing secret (required for /api/v1/auth/*)
php artisan jwt:secret

# Test DB connection
php artisan db:show

# Create tables on your remote DB (only if migrations not already applied)
php artisan migrate
```

If `php artisan db:show` fails, fix `DB_*` / `DB_URL` / SSL settings in `.env` before continuing.

---

## 6. Run the API locally

**Minimal (API only):**

```bash
php artisan serve
```

API base URL:

```
http://localhost:8000/api/v1
```

Health check:

```
http://localhost:8000/up
```

**Full dev stack** (API + queue worker + logs + Vite — optional):

```bash
composer run dev
```

### Point the backoffice frontend at it

In `Demo-Frontend/.env`:

```env
VITE_API_BASE_URL=http://localhost:8000/api/v1
VITE_USE_API_AUTH=true
```

---

## 7. Docker (optional)

If you prefer not to install PHP locally:

```bash
cd /Users/admin/Documents/Projects/Demo-backend
cp .env.example .env
# edit .env with your DB credentials first

docker build -t demo-backend .
docker run --rm -p 8000:8000 --env-file .env demo-backend
```

Note: you still need to run migrations once (from a machine that can reach the DB):

```bash
php artisan migrate
# or: docker run --rm --env-file .env demo-backend php artisan migrate
```

---

## 8. Quick smoke test

```bash
# Health
curl http://localhost:8000/up

# Login (after you have a user in DB)
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"your@email.com","password":"yourpassword"}'
```

---

## 9. Common issues

| Problem | Fix |
|---------|-----|
| `No application encryption key` | `php artisan key:generate` |
| `JWT_SECRET` / token errors | `php artisan jwt:secret` |
| `SQLSTATE[HY000] [2002]` connection refused | Wrong `DB_HOST` / port / VPN / security group |
| SSL certificate errors | Set `MYSQL_ATTR_SSL_CA` and see SSL section above |
| `php: command not found` | Install PHP: `brew install php` |
| Migrations fail on remote DB | DB user needs CREATE/ALTER rights, or run migrations from CI |
| CORS blocked from Vercel back-office | Redeploy API after updating `config/cors.php`; allows `https://demo-backoffice-lac.vercel.app` |

### CORS (deployed API + Vercel frontend)

The browser blocks `https://demo-backoffice-lac.vercel.app` → `https://demo-fintech.onrender.com` unless the API returns `Access-Control-Allow-Origin`. Defaults live in `config/cors.php` (includes that Vercel URL + `*.vercel.app` previews). **Redeploy the backend on Render** after pulling this change, then run `php artisan config:clear` on deploy if you cache config.

---

## 10. API routes (reference)

| Method | Path |
|--------|------|
| POST | `/api/v1/auth/register/email` |
| POST | `/api/v1/auth/register/verify-email` |
| POST | `/api/v1/auth/register/complete` |
| POST | `/api/v1/auth/login` |
| GET | `/api/v1/auth/me` (Bearer token) |
| GET | `/api/v1/wallet/balance` (Bearer token) |

API docs (Scramble) may be available when configured — check `/docs/api` if enabled in your environment.
