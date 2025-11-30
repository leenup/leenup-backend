# API

The API will be here.

## Authentication

- Access tokens (JWT) are now returned in an **`access_token` HttpOnly cookie** on `/auth` and `/api/token/refresh` responses.
- The cookie mirrors the JWT TTL (1h), is `SameSite=Lax`, `Secure`, and `HttpOnly`, and keeps the token in the JSON body for backward compatibility.
- Upcoming step: add a CSRF proof token/guard for requests that rely on the cookie being sent automatically.

Refer to the [Getting Started Guide](https://api-platform.com/docs/distribution) for more information.
