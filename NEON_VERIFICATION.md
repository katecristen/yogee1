# Neon verification — 2026-10-23

Target: organization `org-frosty-hat-01420671`, project
`billowing-meadow-47530436`, branch `production` (`br-odd-grass-azb5x116`).
Commands run from this `Yogee - Copy` application directory.

## Deployment — exit 0

```text
neon deploy --env .env.local

Applied changes
  ~ function hello

Function URLs
  • hello: https://br-odd-grass-azb5x116-hello.compute.c-3.ap-southeast-1.aws.neon.tech/

Utilized services: Postgres, Neon Auth, Object Storage, Functions, Realtime
Pulled 12 Neon variables into .env.local
```

The native Function URL deliberately returns 401 without both the server
origin secret and a valid customer JWT. Customers access it through the PHP API.
AI Gateway is excluded; no plan upgrade was performed.

## Database migration — exit 0

```text
npm run db:migrate
PASS PostgreSQL schema applied; packages: 3
```

## Build — exit 0

```text
npm run build
PASS build: TypeScript checked, 28 PHP files linted, browser and Function bundles generated.
```

## Running app

```text
npm start
PHP 8.2.12 Development Server (http://127.0.0.1:8080) started
```

## Live service E2E — exit 0

```text
npm run test:e2e
PASS access controls: private files, app API, Function secret and JWT
PASS Neon Auth: sign-up, sign-in, invalid password, session restoration
PASS Postgres: package reads, reservation insert/read, server pricing, profile update
PASS Object Storage + Functions: upload/list/download/delete, byte equality, ownership and file validation
PASS Realtime: initial rows, committed reservation change over WebSocket, sealed query renewal
PASS Neon Auth: sign-out and protected access denied
PASS account consistency: invalid DOB, missing-profile recovery, failed identity switch, admin deletion and re-registration
PASS 39 end-to-end assertions across all five services
Test accounts and reservations cleaned up.
```

Tests use actual deployed services and real WebSocket subscriptions. Negative
cases intentionally request invalid credentials, unavailable private paths,
another customer's resources, an invalid date, and an unsupported attachment.

## Standalone browser verification — exit 0

Playwright exercised the actual login form and dashboard in Chromium, then
repeated the layout checks at 1440px and 390px widths:

```text
PASS browser: real login form, dashboard session reload, live reservation insert/cancel, attachment upload/download/delete, logout; desktop/mobile rendered without overflow or JS errors.
```

Screenshots: `build/qa-dashboard-desktop.png` and
`build/qa-dashboard-mobile.png`. The test also confirms that a file download
does not stop subsequent live reservation updates.

## Corrected failures and limitations

- Initial AI Gateway deployment failed because the organization is on Free.
  It was removed as requested; the remaining five services deployed.
- The initial agent-skills subprocess required Node 22.20+ while the host uses
  22.17.1. Neon CLI and agent plugins installed successfully; the installed Neon
  skills were consulted and redundant agent setup was skipped on retry.
- Initial `npm run build` failed because the PHP project had no build script.
  The new build performs PHP linting, TypeScript checks and integration bundling.
- XAMPP's older PostgreSQL library needed a CA bundle and explicit Neon endpoint
  routing. The final connection verifies TLS certificates and hostnames.
- Managed Auth required the app Origin header and trusted domains. Both local
  origins are now configured; Realtime has the same origin allowlist.
- Browser testing found attachment downloads prematurely closed the live
  subscription. Cleanup now uses `pagehide`, and the browser can receive
  reservation changes after a download.
- The extended API test initially outlived its 60-second sealed query. The test
  now renews it throughout, as the application already does.
- Parallel browser/API runs caused navigation timeouts on the single-threaded
  PHP development server. Final browser verification runs separately.
- Browser logout revealed a race between the intended home navigation and a
  pending live-query renewal's sign-in redirect. Logout now stops the live
  subscription first; the complete browser flow subsequently passed.
- No legacy MySQL dump/data was supplied, so previous data/admins were not
  transferred. No default admin account was created.
- The PHP frontend runs locally. Public frontend hosting/custom domain and
  Apache deployment have not been tested. See `NEON_SETUP.md` for configuration.
- Existing inventory/report screens reference absent PHP endpoints and are
  outside this Neon integration.

The read-only code review's findings were addressed, including missing-profile
recovery, managed/local identity consistency and serializing simultaneous
uploads to enforce the ten-attachment limit.
