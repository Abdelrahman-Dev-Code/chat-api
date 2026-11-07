# Real-Time Chat API

A real-time chat REST API with **Google OAuth** sign-in, built with **Laravel 13**, **PHP 8.5**, **PostgreSQL** and **Laravel Reverb** (WebSockets).

| Concern            | Choice                                              |
| ------------------ | --------------------------------------------------- |
| Framework          | Laravel 13 / PHP 8.5                                |
| Database           | PostgreSQL (migrations + seeders)                   |
| Google sign-in     | Laravel Socialite                                   |
| API authentication | Laravel Sanctum (bearer tokens)                     |
| Authorization      | Laravel Policies (`MessagePolicy`)                  |
| Real time          | Laravel Reverb (Pusher protocol, `laravel-echo` on clients) |
| Tests              | PHPUnit, 100% line coverage (CI gate: 80%)          |

Only three first-party packages were added to the Laravel skeleton: `laravel/sanctum`, `laravel/socialite` and `laravel/reverb`.

---

## Architecture

```
app/
├── Events/              MessageEvent (base) → MessageSent, MessageUpdated, MessageDeleted
├── Http/
│   ├── Controllers/     AuthController, MessageController   (thin: HTTP in, resource out)
│   ├── Requests/        ListMessagesRequest, SaveMessageRequest (validation)
│   └── Resources/       MessageResource, UserResource, ProfileResource (JSON shape)
├── Models/              User, Message (SoftDeletes, #[Scope] fromUsers)
├── Policies/            MessagePolicy (only the author may edit/delete)
└── Services/            MessageService, GoogleAuthService (use cases / business logic)
```

A request flows **Route → Middleware (`auth:sanctum`) → Form Request (validation) → Policy (`#[Authorize]`) → Controller → Service → Model / Event → Resource**.

Design patterns used:

- **Service Layer**: `MessageService` and `GoogleAuthService` hold the use cases, so controllers stay thin and the logic is unit-testable.
- **Policy**: ownership rules live in `MessagePolicy`, attached with `#[UsePolicy]` and enforced with the controller `#[Authorize]` attribute.
- **Observer / Pub-Sub**: every write dispatches a broadcast event that Reverb pushes to subscribed clients.
- **Template Method**: `MessageEvent` defines the channel and payload flow; subclasses provide the event name and payload.
- **Data Transformer**: API Resources decouple the JSON contract from the database schema.

Laravel 13 / PHP 8.5 features used: model attributes (`#[Fillable]`, `#[Hidden]`, `#[UsePolicy]`, `#[Scope]`), controller attributes (`#[Authorize]`), container attributes (`#[CurrentUser]`), typed class constants, `new` without parentheses in chains, and the pipe operator (`|>`).

### Database

| Table                    | Columns                                                                                   |
| ------------------------ | ----------------------------------------------------------------------------------------- |
| `users`                  | `id`, `name`, `email` (unique), `google_id` (unique), `avatar`, `email_verified_at`, `password` (nullable), timestamps |
| `messages`               | `id`, `content`, `user_id` (FK → users, cascade), `created_at`, `updated_at`, `deleted_at` |
| `personal_access_tokens` | Sanctum API tokens                                                                        |

`cache`, `jobs` and `sessions` are the standard Laravel support tables (OAuth state cache and broadcast queue).

---

## Getting started

Requirements: PHP 8.5 (with `pdo_pgsql`), Composer, PostgreSQL.

```bash
git clone <repo> chat-api && cd chat-api
cp .env.example .env        # then fill in DB_* and GOOGLE_* values
composer setup              # install, generate key, migrate and seed
composer dev                # starts the API, queue worker and Reverb together
```

- API: `http://localhost:8000/api`
- WebSocket (Reverb): `ws://localhost:8080`

### Google OAuth credentials

1. Go to [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials) and create an **OAuth client ID** (type: *Web application*).
2. Add the authorized redirect URI `http://localhost:8000/api/auth/google/callback`.
3. Put the client ID and secret into `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` in `.env`.

### Docker (optional)

```bash
cp .env.example .env   # fill in GOOGLE_* and set APP_KEY (php artisan key:generate --show)
docker compose up --build
```

This starts PostgreSQL, the API (`:8000`), Reverb (`:8080`) and a queue worker.

---

## API

All responses are JSON. Protected endpoints need `Authorization: Bearer <token>`.

| Method   | Endpoint                     | Auth | Description                                         |
| -------- | ---------------------------- | ---- | --------------------------------------------------- |
| `GET`    | `/api/auth/google/redirect`  | –    | Returns the Google consent URL `{ "url": "..." }`   |
| `GET`    | `/api/auth/google/callback`  | –    | Google redirects here; returns `{ token, user }`    |
| `GET`    | `/api/auth/me`               | ✔    | Current user's profile                              |
| `POST`   | `/api/auth/logout`           | ✔    | Revokes the current token                           |
| `GET`    | `/api/messages`              | ✔    | Latest messages, 10 per page                        |
| `POST`   | `/api/messages`              | ✔    | Send a message                                      |
| `PATCH`  | `/api/messages/{id}`         | ✔    | Edit **your own** message                           |
| `DELETE` | `/api/messages/{id}`         | ✔    | Soft-delete **your own** message                    |
| `POST`   | `/api/broadcasting/auth`     | ✔    | Authorizes the WebSocket private channel            |

### Sign in

1. `GET /api/auth/google/redirect`, then open the returned `url`.
2. After consent, Google calls `/api/auth/google/callback?code=…&state=…`, which responds with:

```json
{
  "token": "1|Xf3k...",
  "token_type": "Bearer",
  "user": { "id": 1, "name": "Sara", "avatar": "https://...", "email": "sara@example.com" }
}
```

### Messages

```http
GET /api/messages?page=2&user_ids[]=1&user_ids[]=4
```

Results are newest first, 10 per page. The optional `user_ids[]` filter limits results to the given authors. Responses include `data`, `links` and `meta` (pagination).

```http
POST /api/messages
Content-Type: application/json

{ "content": "Hello everyone" }
```

`content` is required, must be a string, and may be at most 2000 characters (whitespace-only content is rejected). The author is always taken from the token, never from the request body.

| Status | Meaning                                       |
| ------ | --------------------------------------------- |
| `401`  | Missing or invalid token / failed OAuth check |
| `403`  | Editing or deleting someone else's message    |
| `404`  | Message does not exist or was deleted         |
| `422`  | Validation error                              |

---

## Real time

Every authenticated client subscribes to the private channel **`chat`** and receives:

| Event             | Payload                       |
| ----------------- | ----------------------------- |
| `message.sent`    | the full message (+ author)   |
| `message.updated` | the full message (+ author)   |
| `message.deleted` | `{ "id": 42 }`                |

The sender's own connection is excluded (`toOthers()`) when the client sends the `X-Socket-ID` header, because the sender already has the HTTP response.

Example client with `laravel-echo` and `pusher-js`:

```js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

const echo = new Echo({
  broadcaster: 'reverb',
  key: 'change-me-key',            // REVERB_APP_KEY
  wsHost: 'localhost',
  wsPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: 'http://localhost:8000/api/broadcasting/auth',
  auth: { headers: { Authorization: `Bearer ${token}` } },
});

echo.private('chat')
  .listen('.message.sent', (message) => console.log('new', message))
  .listen('.message.updated', (message) => console.log('edited', message))
  .listen('.message.deleted', ({ id }) => console.log('deleted', id));
```

Event payloads are captured when the event is created, so a queued broadcast always carries the message as it was at that moment. A fast edit can never overtake the original `message.sent`.

---

## Security

- **Middleware**: every chat endpoint and the WebSocket channel authorization sit behind `auth:sanctum`. The OAuth endpoints are rate limited.
- **Policies**: `MessagePolicy` allows only the author to edit or delete a message.
- **Spoof-proof Google callback** (`GoogleAuthService`):
  1. A random one-time `state` is stored server-side (10 min TTL) and must match on the callback. Forged, expired or replayed states are rejected.
  2. The user profile never comes from the client. The `code` is exchanged server-to-server with Google using the client secret, so it only works for this app's client ID.
  3. Only Google accounts with `email_verified = true` are accepted.
- **Privacy**: other users' e-mail addresses are never exposed in messages or broadcasts. Only `/auth/me` and the login response return the user's own e-mail.
- **Mass assignment**: only `content` is fillable on messages, and the author comes from the token.

---

## Testing

```bash
composer test                 # run the suite
composer test:coverage        # run with coverage, fails below 80%
```

Coverage requires the `pcov` or `xdebug` extension. The suite has 47 tests and covers 100% of `app/`. It runs on in-memory SQLite by default and was also verified against PostgreSQL.

- `tests/Unit`: policy, broadcast events, `MessageService`, `GoogleAuthService` (Google is mocked)
- `tests/Feature`: HTTP endpoints, validation, authorization, channel auth

---

## Conventional Commits

The history follows [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `test:`, `docs:`, `build:`, `ci:`, `chore:`.
