# Real-Time Chat — Laravel 13, Livewire 4 & Reverb

A complete real-time chat application with **two entry points sharing one domain layer**:

- a **server-rendered Livewire UI** for browsers, and
- a **REST API** for external clients.

Both use **Google OAuth**, **PostgreSQL**, **Laravel Sanctum**, **Laravel Reverb** (WebSockets) and the same `MessageService`, policies, validation rules and broadcast events.

---

## Highlights

### Browser UI (Livewire)

- Google sign-in with a session-bound, one-time OAuth `state`.
- Protected `/chat` page with the signed-in user's name, avatar and a sign-out button.
- Responsive transcript with sender name, avatar, content, relative time and an `edited` badge.
- Exactly **10 messages per page** plus **“Load older messages”** (prepends the previous page).
- **Everyone / Only my messages** filter.
- Send, inline edit and soft-delete your own messages; authorization errors are shown inline.
- Real-time updates over a private Reverb channel — no refresh required.
- Empty, loading and error states; Enter sends, Shift+Enter inserts a newline.
- Mobile-first custom CSS (no Tailwind), safe-area padding and reduced-motion support.

### REST API

- Sanctum bearer-token authentication.
- `GET/POST /api/messages`, `PATCH/DELETE /api/messages/{message}`.
- Exactly **10 messages per page**, newest first.
- Optional author filter: `?user_ids[]=1&user_ids[]=2`.
- Ownership enforced by `MessagePolicy`; only the author may edit or delete.
- Soft deletes (`deleted_at`); deleted messages disappear from listings.
- Validation shared with the UI (`SaveMessageRequest::contentRules()`).
- Real-time broadcasts for create, update and delete.

### Security & quality

- One-time OAuth state with a 10-minute TTL; web states are additionally **bound to the browser session**.
- Google accounts must have a **verified e-mail**; accounts are linked by Google ID first, then verified e-mail.
- Author is always taken from the token/session, never from the request body.
- Rate limits: OAuth 20/min and Livewire sending 30/min. Message API routes do not currently define a route-specific limit.
- Other users' e-mail addresses are never exposed in messages or broadcasts.
- **123 tests / 473 assertions** in the current suite, Laravel Pint formatting, and an **80% coverage gate** in CI.

---

## Tech stack

| Concern            | Choice                                              |
| ------------------ | --------------------------------------------------- |
| Framework          | Laravel 13.40 / PHP 8.3+                            |
| Database           | PostgreSQL 17+ (SQLite in-memory for tests)         |
| Browser UI         | Livewire 4.4 + Blade + custom CSS                   |
| API authentication | Laravel Sanctum 4.3 (bearer tokens)                 |
| Google sign-in     | Laravel Socialite 5.31                              |
| Real time          | Laravel Reverb 1.12 (Pusher protocol)               |
| Front-end build    | Vite 7 + `laravel-vite-plugin` 2 + `laravel-echo` 2  |
| Authorization      | Laravel Policies (`MessagePolicy`)                  |
| Tests / style      | PHPUnit 12, Mockery, Laravel Pint                   |
| Local orchestration| Docker Compose (PostgreSQL, API, Reverb, queue)     |

---

## Architecture

```
app/
├── Events/                  MessageEvent (base) → MessageSent, MessageUpdated, MessageDeleted
├── Http/
│   ├── Controllers/         AuthController, MessageController        (thin HTTP layer)
│   ├── Requests/            ListMessagesRequest, SaveMessageRequest  (validation)
│   └── Resources/           MessageResource, UserResource, ProfileResource
├── Livewire/
│   ├── Auth/Login.php       Google sign-in screen
│   └── Chat.php             transcript, pagination, send/edit/delete, Echo listeners
├── Models/                  User, Message (SoftDeletes, #[Scope] fromUsers)
├── Policies/                MessagePolicy (author-only update/delete)
└── Services/
    ├── GoogleAuthService.php API + web OAuth, state handling, safe account linking
    └── MessageService.php    shared chat use cases (list/send/update/delete)
```

```
routes/
├── api.php        Sanctum-protected REST API + API OAuth callbacks
├── web.php        Livewire pages, web OAuth callbacks, session logout, web broadcast auth
└── channels.php   private "chat" channel authorization

resources/
├── css/app.css                 responsive design system
├── js/app.js                   shared theme, locale, and OAuth behavior
├── js/chat-app.js              chat-only Echo/Reverb and workspace entry point
└── views/
    ├── layouts/app.blade.php
    ├── components/avatar.blade.php
    ├── livewire/auth/login.blade.php
    └── livewire/chat.blade.php
```

**API request flow:**
`Route middleware (OAuth throttle or Sanctum auth) → Form Request → Controller / Policy → MessageService → Model / broadcast event → API Resource`

**Browser request flow:**
`Route → auth (session) → Livewire component → Service / Policy → Blade morph → Echo event`

Design choices:

- **Service layer** — `MessageService` and `GoogleAuthService` own the use cases; controllers and Livewire components stay thin.
- **Policy** — ownership is enforced identically for HTTP and Livewire actions.
- **Pub/Sub** — every write dispatches a `ShouldBroadcast` event to Reverb.
- **Template Method** — `MessageEvent` captures the payload at construction time and lets subclasses choose the event name/payload.
- **API Resources** — the JSON contract is independent of the database schema.
- **No duplicated validation** — `SaveMessageRequest::contentRules()` is reused by the Livewire component.

See [docs/backend.md](./docs/backend.md) for the backend code map, actual API
routes and payloads, authentication flow, rate limits, and database setup.
For a complete Arabic study guide and discussion preparation, see
[مذكرة فهم التطبيق](./docs/مذكرة-فهم-التطبيق.md) and
[التحضير لمناقشة المشروع](./docs/التحضير-لمناقشة-المشروع.md).
To exercise the REST API, import the [Postman collection](./postman/Chat-API.postman_collection.json)
and follow its setup notes in the backend guide.

---

## Database

| Table                    | Columns                                                                                                            |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------ |
| `users`                  | `id`, `name`, `email` (unique), `google_id` (unique), `avatar`, `email_verified_at`, `password` (nullable), timestamps |
| `messages`               | `id`, `user_id` (FK → users, cascade), `content`, timestamps, `deleted_at`; indexed on `user_id`, `created_at`, `(user_id, created_at)` |
| `personal_access_tokens` | Sanctum API tokens                                                                                                 |
| `sessions`, `cache`, `jobs` | Laravel support tables (DB session/cache/queue drivers)                                                        |

Factories (`UserFactory`, `MessageFactory`) and `DatabaseSeeder` are included. The seeder creates 5 users with 10 messages each.

---

## Getting started

### Requirements

- **PHP 8.3+** with `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json` (and `pcntl` on Unix for Reverb/queue workers).
- **Composer 2**
- **PostgreSQL 15+** (17 is used by Docker Compose)
- **Node.js 20.19+ or 22.12+** and npm — only needed to build the front-end assets.
- Optional: Docker + Docker Compose.

### 1. Install

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Create the PostgreSQL database (default name `chat_app`) and set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`.

```bash
php artisan migrate --seed
```

### 2. Build the front end

```bash
npm install
npm run build          # production bundles in public/build
# or, during development:
npm run dev            # Vite dev server with hot reload
```

> `public/build` is generated and git-ignored. Run `npm run build` before serving the app in production.

### 3. Run

```bash
composer dev
```

`php artisan dev` starts the Laravel server, queue listener, Vite, Reverb and (on Unix) log tailing in one terminal.

Or run the processes separately:

```bash
php artisan serve            # http://localhost:8000
php artisan reverb:start     # ws://localhost:8080
php artisan queue:work       # if QUEUE_CONNECTION=database
npm run dev                  # Vite dev server
```

Open **http://localhost:8000** → you are redirected to `/login` → “Continue with Google” → `/chat`.

### 4. Google OAuth credentials

1. Open [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials) and create an **OAuth client ID** of type *Web application*.
2. Register **both** authorized redirect URIs:
   - **API clients:** `http://localhost:8000/api/auth/google/callback` (`GOOGLE_REDIRECT_URI`)
   - **Browser UI:** `http://localhost:8000/auth/google/callback` (`GOOGLE_WEB_REDIRECT_URI`)
3. Set `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`.

The API callback returns a JSON Sanctum token; the web callback logs the browser in and redirects to `/chat`.

### 5. Reverb / WebSocket configuration

```dotenv
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=chat-api
REVERB_APP_KEY=change-me-key
REVERB_APP_SECRET=change-me-secret

# How the Laravel server reaches Reverb (internal Docker hostname in compose).
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# How the browser reaches Reverb. Only the public app key is exposed.
REVERB_PUBLIC_HOST=localhost
REVERB_PUBLIC_PORT=8080
REVERB_PUBLIC_SCHEME=http

REVERB_ALLOWED_ORIGINS=http://localhost:8000,http://127.0.0.1:8000
```

`REVERB_APP_SECRET` stays server-side. The chat page injects only the public key, host, port, scheme and CSRF token into `window.chatConfig`.

### 6. Docker

```bash
cp .env.example .env
# set APP_KEY, GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and DB_PASSWORD
docker compose up --build
```

Compose starts:

| Service  | Address              | Purpose                                      |
| -------- | -------------------- | -------------------------------------------- |
| `api`    | `localhost:8000`     | Laravel + Livewire UI; runs migrations first |
| `reverb` | `localhost:8080`     | WebSocket server                             |
| `db`     | internal `db:5432`   | PostgreSQL 17 with a persistent volume       |
| `queue`  | —                    | Queue worker                                 |

The image is multi-stage: Node builds the Vite assets, then the PHP runtime copies `public/build`.

Set `APP_URL=http://localhost:8000`, keep `REVERB_PUBLIC_HOST=localhost`, and register the Docker callback URLs in Google Cloud Console.

---

## Browser UI

| Route                          | Access     | Description                                       |
| ------------------------------ | ---------- | ------------------------------------------------- |
| `/`                            | public     | Redirects to `/chat` or `/login`                  |
| `/login`                       | guest      | Livewire Google sign-in screen                    |
| `/auth/google/redirect`        | guest      | Sends the browser to Google with a session state  |
| `/auth/google/callback`        | guest      | Verifies OAuth, starts a session, redirects to chat |
| `/chat`                        | auth       | Livewire chat room                                |
| `POST /logout`                 | auth       | Ends the browser session                          |
| `POST /broadcasting/auth`      | web + auth | Authorizes the browser's private WebSocket channel |

Chat behavior:

- The first page loads the **10 newest** messages, ordered oldest → newest for display.
- **Load older messages** prepends the previous 10, preserving the scroll position.
- **Only my messages** reloads the first page filtered to the current user.
- Editing opens an inline textarea; saving updates the transcript and broadcasts `message.updated`.
- Deleting is confirmed in the browser, soft-deletes the row, and broadcasts `message.deleted`.
- Incoming Echo events are merged by message ID, so duplicates from the sender's own connection are harmless.
- The transcript stays pinned to the bottom only when the reader was already at the bottom.

---

## REST API

All responses are JSON. Send `Accept: application/json`. Protected endpoints
require the Sanctum access token returned during sign-in.

| Method   | Endpoint                     | Auth | Description                                      |
| -------- | ---------------------------- | ---- | ------------------------------------------------ |
| `GET`    | `/api/auth/google/redirect`  | —    | Returns the Google consent URL `{ "url": "..." }` |
| `GET`    | `/api/auth/google/callback`  | —    | Exchanges `code` + `state`, returns a token      |
| `GET`    | `/api/auth/me`               | ✔    | Current user's profile                           |
| `POST`   | `/api/auth/logout`           | ✔    | Revokes the current token                        |
| `GET`    | `/api/messages`              | ✔    | Latest messages, exactly 10 per page             |
| `POST`   | `/api/messages`              | ✔    | Send a message                                   |
| `PATCH`  | `/api/messages/{message}`    | ✔    | Edit **your own** message                        |
| `DELETE` | `/api/messages/{message}`    | ✔    | Soft-delete **your own** message                 |
| `POST`   | `/api/broadcasting/auth`     | ✔    | Authorizes the WebSocket private channel (token) |

### Sign in (API)

```http
GET /api/auth/google/redirect
```

```json
{ "url": "https://accounts.google.com/o/oauth2/auth?client_id=...&state=..." }
```

Open that URL. Google redirects to the callback:

```http
GET /api/auth/google/callback?code=...&state=...
```

```json
{
  "token": "1|Xf3k...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "name": "Sara",
    "avatar": "https://...",
    "email": "sara@example.com"
  }
}
```

The `state` is one-time and expires after 10 minutes.

### List messages

```http
GET /api/messages?page=2
GET /api/messages?user_ids[]=1&user_ids[]=4
```

- Newest first, **exactly 10 per page** (`page` is optional, default `1`).
- `user_ids[]` accepts up to 50 positive IDs; duplicates are removed.
- There is intentionally **no `per_page` parameter**.
- Deleted messages are never returned.

Response (Laravel paginator):

```json
{
  "data": [
    {
      "id": 42,
      "content": "Hello everyone",
      "user": { "id": 1, "name": "Sara", "avatar": "https://..." },
      "created_at": "2026-11-08T10:15:00+00:00",
      "updated_at": "2026-11-08T10:16:12+00:00"
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "per_page": 10, "total": 24 }
}
```

### Send / edit

```http
POST /api/messages
Content-Type: application/json

{ "content": "Hello everyone" }
```

```http
PATCH /api/messages/42
Content-Type: application/json

{ "content": "Hello again" }
```

`content` is required, must be a string, and is limited to **2,000 characters**. Whitespace-only content is rejected. The author always comes from the token, never from the body.

### Delete

```http
DELETE /api/messages/42
```

Returns `204 No Content`. The row is soft-deleted and `message.deleted` is broadcast.

### Status codes

| Status | Meaning                                       |
| ------ | --------------------------------------------- |
| `200`  | Success (list, profile, or edit)              |
| `201`  | Message created                               |
| `204`  | Deleted                                       |
| `401`  | Missing/invalid token or failed OAuth check   |
| `403`  | Editing or deleting someone else's message    |
| `404`  | Message does not exist or was deleted         |
| `422`  | Validation error                              |
| `429`  | Rate limit exceeded                           |

---

## Real time (Reverb)

Every authenticated client joins the private channel **`chat`**:

| Event             | Payload                                     |
| ----------------- | ------------------------------------------- |
| `message.sent`    | Full message + author (`MessageResource`)   |
| `message.updated` | Updated message resource + public author profile |
| `message.deleted` | `{ "id": 42 }`                              |

Broadcasts use `toOthers()`. The event payload is captured when the event object is constructed, so a queued broadcast always carries the message as it was at that moment.

### Browser (session auth)

The Livewire page initializes Echo/Reverb from `resources/js/chat-app.js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: window.chatConfig.key,             // public REVERB_APP_KEY
  wsHost: window.chatConfig.host,
  wsPort: window.chatConfig.port,
  wssPort: window.chatConfig.port,
  forceTLS: window.chatConfig.scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  authEndpoint: '/broadcasting/auth',      // web + auth middleware
  auth: {
    headers: {
      'X-CSRF-TOKEN': window.chatConfig.csrfToken,
      'X-Requested-With': 'XMLHttpRequest',
    },
  },
});

echo.private('chat')
  .listen('.message.sent', (message) => { /* ... */ })
  .listen('.message.updated', (message) => { /* ... */ })
  .listen('.message.deleted', ({ id }) => { /* ... */ });
```

### External clients (bearer token)

Use `/api/broadcasting/auth` for private-channel authorization and attach the
Sanctum token returned at sign-in using HTTP bearer authentication. Do not use
the Reverb application secret as a user token.

---

## Security

- **OAuth CSRF / replay protection** — a random 40-character `state` is stored server-side for 10 minutes and consumed once (`cache->pull`). Forged, expired or replayed states are rejected.
- **Login CSRF protection for the web UI** — the state is stored as a SHA-256 fingerprint of the browser session token and compared with `hash_equals`; a state issued for another browser session is rejected.
- **Server-side code exchange** — the profile never comes from the client. The authorization code is exchanged server-to-server with the client secret.
- **Verified e-mail only** — Google accounts must report `email_verified === true`.
- **Safe account linking** — match by `google_id`, then by verified e-mail, then create a new user. This avoids duplicate accounts without trusting unverified data.
- **Authorization** — only the author may update or delete; `MessagePolicy` is enforced for both API and Livewire actions.
- **Mass assignment** — only `content` is fillable on `Message`; the author is taken from the token/session.
- **Privacy** — `UserResource` exposes `id`, `name`, `avatar` only. E-mail is returned only for the authenticated user (`ProfileResource`).
- **Rate limiting** — OAuth endpoints 20/min; Livewire sending 30/min per user. Message API routes have no route-specific limiter currently.
- **Soft deletes** — deleted messages are excluded from queries but retained for audit/history.
- **Secrets** — `REVERB_APP_SECRET` and Google secrets never reach the browser.

---

## Testing & quality

```bash
composer test                 # in-memory SQLite
composer test:coverage        # adds --coverage --min=80 (needs Xdebug or PCOV)
vendor/bin/pint               # format
vendor/bin/pint --test        # check style (used by CI)
```

Coverage requires the **Xdebug** or **PCOV** PHP extension; the GitHub Actions workflow installs PCOV and enforces **≥ 80%** coverage, then runs Pint in test mode.

Latest local verification: **123 tests / 473 assertions**, **95.04% line coverage** (Xdebug), and PostgreSQL connectivity and migration checks.

- `tests/Unit` — `MessagePolicy`, broadcast events, `MessageService`, `GoogleAuthService` (Google mocked).
- `tests/Feature` — auth API, message API, channel authorization, Livewire chat, web OAuth/session.

Tests do not need PostgreSQL, Node or Reverb: they use SQLite `:memory:`, `BROADCAST_CONNECTION=null`, and `withoutVite()`.

---

## Manual verification checklist

1. **Web UI** — with real Google credentials, open `/`, sign in, land on `/chat`.
2. **Send** — type a message and press Enter; it appears immediately and the composer clears.
3. **Real time** — open `/chat` in a second browser/incognito window; new messages and edits appear without refreshing.
4. **Edit/delete** — use the buttons on your own message; confirm the `edited` badge and removal on both clients.
5. **Pagination** — seed more than 10 messages; “Load older messages” prepends 10 and keeps the scroll position.
6. **Filter** — switch to “Only my messages”; the transcript reloads with only your messages.
7. **Authorization** — verify another user cannot edit/delete your message (buttons are hidden; API returns `403`).
8. **API** — complete the OAuth token flow, then exercise list/send/edit/delete with `curl` or an HTTP client.
9. **Deleted messages** — after a delete, `GET /api/messages` no longer includes the message.
10. **Quality gates** — `composer test`, `composer test:coverage`, `vendor/bin/pint --test`.

---

## Troubleshooting

| Symptom | Fix |
| ------- | --- |
| `Vite manifest not found` | `npm install && npm run build` (or run `npm run dev`) |
| WebSocket never connects | Ensure `php artisan reverb:start` is running; check `REVERB_PUBLIC_HOST/PORT/SCHEME` and add the browser origin to `REVERB_ALLOWED_ORIGINS` |
| `redirect_uri_mismatch` | Register both exact callback URLs in Google Cloud Console |
| `could not find driver` | Enable `pdo_pgsql` in `php.ini` |
| `419 Page Expired` on broadcast auth | Reload the chat page; the CSRF/session expired |
| `SQLSTATE[08006]` in Docker | Wait for the `db` health check; verify `DB_HOST=db` inside compose |
| Queue jobs do not run | Start `php artisan queue:work` or use `QUEUE_CONNECTION=sync` locally |
| PowerShell blocks `npm` | Use `npm.cmd` (execution policy blocks `npm.ps1`) |

---

## Conventions

The history follows [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `test:`, `docs:`, `build:`, `ci:`, `chore:`.
