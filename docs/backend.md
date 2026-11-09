# Backend guide

This project keeps the browser chat and REST API on the same Laravel domain
layer. Browser requests use Laravel sessions and Livewire; external clients use
Sanctum bearer tokens. PostgreSQL is the application database. Automated tests
override it with an in-memory SQLite database.

## Code map

| Location | Responsibility |
| --- | --- |
| `routes/web.php` | Login, locale, browser OAuth, chat, logout, and session broadcast authorization routes |
| `routes/api.php` | API OAuth, token-protected profile/logout, and message routes |
| `routes/channels.php` | Authorization for the private `chat` broadcast channel |
| `app/Http/Controllers` | HTTP coordination; delegates chat work to services |
| `app/Http/Requests` | Input validation and normalized message-list filters |
| `app/Http/Resources` | Stable JSON shapes for messages and profiles |
| `app/Livewire` | Browser chat state and UI actions |
| `app/Services/GoogleAuthService.php` | Google code exchange, one-time state, verified-account checks, and account linking |
| `app/Services/MessageService.php` | Shared message listing, creation, updates, deletes, and broadcasts |
| `app/Models` | Eloquent records, relationships, and query scopes |
| `app/Policies/MessagePolicy.php` | Author-only message update and delete authorization |
| `app/Events` | Immutable broadcast payloads for sent, updated, and deleted messages |
| `database/migrations` | PostgreSQL-compatible application and framework schema |

### Request flow

**REST API:** route middleware → Form Request validation → controller →
`MessageService` / policy → Eloquent model → JSON Resource.

**Browser:** session route → Livewire component → shared service / policy →
Blade view. Reverb events update other connected clients.

Keep HTTP parsing and response formatting in controllers/requests/resources.
Put reusable business behavior in services, and keep persistence constraints in
migrations. The browser and REST flows should call the same service methods
where they perform the same operation.

## REST API

All API routes are under `/api`. Send `Accept: application/json`. Protected
routes require the access token returned by the OAuth callback.

### Endpoints

| Method | Path | Access | Behavior |
| --- | --- | --- | --- |
| `GET` | `/api/auth/google/redirect` | Public | Returns a Google consent URL as `{ "url": "..." }` |
| `GET` | `/api/auth/google/callback?code=...&state=...` | Public | Verifies Google and returns a Sanctum token and profile |
| `GET` | `/api/auth/me` | Authenticated | Returns the authenticated profile |
| `POST` | `/api/auth/logout` | Authenticated | Revokes the current token; returns `204` |
| `GET` | `/api/messages` | Authenticated | Lists the latest messages |
| `POST` | `/api/messages` | Authenticated | Creates a message; returns `201` |
| `PATCH` | `/api/messages/{message}` | Authenticated, author only | Updates a message |
| `DELETE` | `/api/messages/{message}` | Authenticated, author only | Soft-deletes a message; returns `204` |
| `POST` | `/api/broadcasting/auth` | Authenticated | Authorizes access to the private chat channel |

OAuth redirect and callback routes are limited to 20 requests per minute. The
Livewire send action has a separate per-user limit of 30 sends per minute.
Message API routes do not currently define a route-specific limit.

### Google sign-in for API clients

1. Request `GET /api/auth/google/redirect` and open the returned `url`.
2. Google returns `code` and `state` to the configured API callback URL.
3. Send those values to `GET /api/auth/google/callback`.
4. Store the returned token securely and send it as a bearer token on protected
   requests.

OAuth state is single-use and expires after ten minutes. Google must report a
verified e-mail address. The response includes the token and the signed-in
user's profile; other users' e-mail addresses are not included in message data.

Example callback response:

```json
{
  "token": "<sanctum-token>",
  "token_type": "Bearer",
  "user": {
    "id": 12,
    "name": "Example User",
    "avatar": "https://example.test/avatar.png",
    "email": "user@example.test"
  }
}
```

### Messages

List messages, newest first, ten per page:

```http
GET /api/messages?page=2&user_ids[]=12&user_ids[]=18
```

Supported query parameters:

| Parameter | Rules | Default |
| --- | --- | --- |
| `page` | Integer, at least `1` | `1` |
| `user_ids[]` | Up to 50 positive integer user IDs | All authors |

The API does not accept `user_id` or `per_page`. Deleted messages are excluded.
The response is a Laravel resource paginator with `data`, `links`, and `meta`.
Each message contains `id`, `content`, `user` (`id`, `name`, `avatar`),
`created_at`, and `updated_at`.

Create and update requests have the same body:

```json
{ "content": "Hello everyone" }
```

`content` is required, must be a string, and may contain at most 2,000
characters. The request cannot choose the author; the authenticated user is
always used. Only the author may update or delete a message.

Typical responses:

| Status | Meaning |
| --- | --- |
| `200` | Message list, profile, or successful update |
| `201` | Message created |
| `204` | Token revoked or message deleted |
| `401` | Missing/invalid token or invalid OAuth state |
| `403` | User is not the message author |
| `404` | Message does not exist or is soft-deleted |
| `422` | Invalid request fields |
| `429` | Request limit exceeded |

### Real-time events

Authenticated clients subscribe to the private `chat` channel through Reverb.
The API channel authorization URL is `/api/broadcasting/auth`; the browser uses
the session-authenticated `/broadcasting/auth` route.

| Event | Payload |
| --- | --- |
| `message.sent` | Message resource with its public author profile |
| `message.updated` | Updated message resource with its public author profile |
| `message.deleted` | `{ "id": 42 }` |

Message events are sent to other clients (`toOthers`). API message writes
dispatch the broadcast event as part of the service call. The Livewire send
flow publishes after its HTTP response so a slow Reverb connection does not
delay the sender.

## Database and safe local setup

Set `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, and `DB_PASSWORD` in the untracked `.env` file. Never put
credentials in source control or support requests.

Apply the schema with:

```bash
php artisan migrate
```

Tests intentionally use SQLite in memory and do not alter the configured
PostgreSQL database:

```bash
php artisan test
```

## Verification commands

```bash
php artisan route:list --path=api -v
php artisan migrate:status
php artisan test
vendor/bin/pint --test
```

## Postman

Import [`postman/Chat-API.postman_collection.json`](../postman/Chat-API.postman_collection.json)
into Postman. Set `base_url` to the running app URL (default
`http://127.0.0.1:8000`). Complete Google OAuth with the configured callback,
then store the returned token in the collection's `access_token` variable; the
callback request does this automatically when supplied with `google_code` and
`oauth_state`. Run **Create message** before **Edit message** or **Delete
message**; a successful create saves its ID as `message_id`.

Treat `access_token` as a secret. Do not commit or share an exported collection
after adding a real token.
