# Ditco Affiliate Gateway System

> Backend engine for managing affiliate profiles, tracking visitor clicks, and serving landing page data built as a technical assessment for Ditcosoft.

## Stack

| Layer | Technology |
|---|---|
| API | PHP 8.2 + Apache (Pure PHP, no framework) |
| Database | PostgreSQL 16 |
| Microservice | Node.js 18 (zero npm dependencies) |
| Auth | JWT HS256 (hand-rolled) |
| Containers | Docker + Docker Compose |

---

## Architecture

![Architecture Diagram](<Untitled Diagram.drawio.png>)

The PHP API is the single entry point. PostgreSQL is internal-only (not exposed outside Docker). When a new affiliate is registered, PHP fires a POST to the Node.js notification service which logs a simulated email/SMS to the console.

---

## Running the Project

### Requirements
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) - nothing else needed.

### 1. Configure

```bash
cp php-core/.env.example php-core/.env
```

Edit `php-core/.env` and set a strong `JWT_SECRET` (min 32 chars):
```bash
openssl rand -hex 32
```

### 2. Start containers

```bash
docker compose up --build -d
```

### 3. Seed admin user *(run once)*

```bash
docker exec ditco_php_api php /var/www/html/scripts/seed-admin.php
```

Output confirms:
```
[OK] Admin 'admin' seeded.
[OK] Sample affiliate password set (password: affiliate123).
```

### 4. Verify everything is up

```bash
curl http://localhost:8080/affiliate/acme-tech   # PHP API
curl http://localhost:3000/health                 # Notification service
```

| Service | URL |
|---|---|
| PHP API | http://localhost:8080 |
| Notification Service | http://localhost:3000 |
| PostgreSQL | Internal only |

### Stop

```bash
docker compose down        # keep data
docker compose down -v     # full reset (wipes database)
```

---

## API Reference

Base URL: `http://localhost:8080`

### Auth

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/admin/login` | None | Get JWT token |
| POST | `/admin/affiliate` | JWT | Create affiliate |
| GET | `/affiliate/{slug}` | None | Get affiliate + products |
| PUT | `/affiliate/{slug}` | JWT | Update contact info |
| POST | `/click-track` | None | Record a click event |

---

**POST /admin/login**
```json
// Request
{ "username": "admin", "password": "admin123" }

// Response 200
{ "token": "<jwt>" }
```

---

**POST /admin/affiliate** — `Authorization: Bearer <token>`
```json
// Request
{
  "business_name": "Nova Digital Ltd",
  "email": "contact@novadigital.ng",
  "slug": "nova-digital",
  "phone": "+234-800-123-4567",
  "logo_url": "https://example.com/logo.png",
  "password": "nova123"
}

// Response 201
{ "data": { "id": 2, "business_name": "Nova Digital Ltd", "slug": "nova-digital", ... } }
```

---

**GET /affiliate/{slug}**
```json
// Response 200
{
  "data": {
    "id": 1, "business_name": "Acme Technologies", "slug": "acme-tech",
    "products": [
      { "id": 1, "name": "Ditcosoft Pro Suite", "price": "299.99", "destination_url": "..." }
    ]
  }
}
```

---

**PUT /affiliate/{slug}** — `Authorization: Bearer <token>`
```json
// Request (send only fields to update)
{ "phone": "+234-800-999-0000", "business_name": "Acme (Updated)" }

// Response 200
{ "data": { "id": 1, "business_name": "Acme (Updated)", "updated_at": "..." } }
```

---

**POST /click-track**
```json
// Request
{ "affiliate_id": 1 }

// Response 201
{ "message": "Click recorded.", "click_id": 42, "timestamp": "..." }

// Response 429 (rate limit — 10 requests/IP/60s)
{ "error": "Rate limit exceeded. Maximum 10 requests per 60 seconds." }
```

---

## Database Schema

```
+-------------------+
|      ADMINS       |
+-------------------+
| id (PK)           |
| username (UNIQUE) |
| password          |
| created_at        |
+-------------------+


+---------------------------+
|        AFFILIATES         |
+---------------------------+
| id (PK)                   |
| business_name             |
| logo_url                  |
| phone                     |
| email (UNIQUE)            |
| slug (UNIQUE, immutable)  |
| password                  |
| created_at                |
| updated_at                |
+---------------------------+
          | 1
          |
          | many
          v
+---------------------------+
|         PRODUCTS          |
+---------------------------+
| id (PK)                   |
| affiliate_id (FK)         |
| name                      |
| description               |
| price                     |
| destination_url           |
| created_at                |
| updated_at                |
+---------------------------+

          | 1
          |
          | many
          v
+---------------------------+
|        CLICK_LOGS         |
+---------------------------+
| id (PK)                   |
| affiliate_id (FK)         |
| ip_address                |
| user_agent                |
| timestamp                 |
+---------------------------+

* slug is UNIQUE and immutable — used as the URL identifier
```

---

## Security

| Threat | Mitigation |
|---|---|
| SQL Injection | PDO prepared statements, `EMULATE_PREPARES = false` |
| XSS | `htmlspecialchars(ENT_QUOTES)` on all input/output |
| Bot spam | IP rate limiting via COUNT query on `click_logs` → 429 |
| JWT tampering | `hash_equals()` timing-safe HMAC verification |
| Mass assignment | PUT endpoint whitelists only 4 allowed fields |
| Secret exposure | `.env` in `.gitignore`; `src/` outside `DocumentRoot` |

### If a secret is accidentally committed

1. **Rotate** — generate new values immediately, update in your environment.
2. **Invalidate** — changing `JWT_SECRET` invalidates all existing tokens.
3. **Purge from history** — `git filter-repo --path php-core/.env --invert-paths && git push --force`
4. **Audit** — review access logs for any usage during the exposure window.
5. **Prevent** — add `truffleHog` or `git-secrets` as a pre-commit hook.

---

## Testing with Postman

1. Import `postman/DitcoAffiliateGateway.postman_collection.json`
2. Run **Admin Login** first — JWT is auto-saved to `{{jwt_token}}`
3. Run requests in order; send **Track Click** 11+ times to trigger the 429

To watch the notification service fire:
```bash
docker logs -f ditco_notification
```

Expected output when an affiliate is created:
```
────────────────────────────────────────────────────────────
[NOTIFICATION] New Affiliate Registered
  Time          : 2026-04-05T10:00:00.000Z
  Affiliate ID  : 2
  Business Name : Nova Digital Ltd
  Email         : contact@novadigital.ng
  Action        : Welcome email dispatched (simulated)
────────────────────────────────────────────────────────────
```

---

## Git Workflow

```bash
git init && git add . && git commit -m "chore: initial project scaffold"

git checkout -b feature/click-tracking
git add php-core/public/index.php db/schema.sql
git commit -m "feat: add /click-track endpoint with rate limiting"

git checkout main
git merge --no-ff feature/click-tracking -m "feat: merge click-tracking feature"
```
