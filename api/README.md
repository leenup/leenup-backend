# API

The API will be here.

## Authentication

- Access tokens (JWT) are now returned in an **`access_token` HttpOnly cookie** on `/auth` and `/api/token/refresh` responses.
- The cookie mirrors the JWT TTL (1h), is `SameSite=Lax`, `Secure`, and `HttpOnly`, and keeps the token in the JSON body for backward compatibility.
- A CSRF double-submit token is now issued alongside the access token cookie (also echoed in the `X-CSRF-TOKEN` response header). All non-safe requests must include the `X-CSRF-TOKEN` request header matching the `XSRF-TOKEN` cookie when using the cookie-based auth flow.

Refer to the [Getting Started Guide](https://api-platform.com/docs/distribution) for more information.
