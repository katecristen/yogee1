# Yogee on Neon

The application root is this `Yogee - Copy` folder. The existing PHP/HTML app
uses the `production` branch of project `billowing-meadow-47530436`, organization
`org-frosty-hat-01420671`, in AWS Singapore (`ap-southeast-1`).

## Services

| Service | App integration |
| --- | --- |
| Postgres | PHP PDO through the pooled `DATABASE_URL`, TLS certificate verification, PostgreSQL schema and the existing reservation/profile/admin APIs |
| Neon Auth | Customer registration, email/password login, upstream session validation, JWTs for the Function, and logout, bridged to HttpOnly PHP sessions |
| Object Storage | Private `assets` bucket for reservation receipts/documents; PNG, JPEG, PDF, maximum 5 MB, ownership checked on upload/list/download/delete |
| Functions | The existing `hello` slug now runs the protected reservation service in `hello.ts`; it requires the server origin secret and a verified Neon Auth JWT |
| Realtime | Customer-scoped sealed reservation queries update the dashboard over WebSockets; queries renew before expiry and close on page departure |

AI Gateway was removed from `neon.ts` at the user's request because it needs a
paid plan. No plan upgrade was made. The other five services deployed successfully.

## Run and verify

Requirements: Node.js 22+, PHP 8.2+ with PDO PostgreSQL, cURL, Fileinfo and a
trusted CA bundle. `PHP_BIN` can select a PHP executable. `npm start` enables
the PostgreSQL extension without changing the machine-wide PHP configuration.
The installed Neon skills installer requires Node 22.20+; the application runs
on the machine's existing Node 22.17.1.

```powershell
npm ci
npm run db:migrate
npm run build
npm start
# In another terminal, while the app is running:
npm run test:e2e
```

Open http://127.0.0.1:8080. The app serves through `router.php`, which blocks
credentials, source directories and legacy database/debug utilities. For Apache,
`.htaccess` routes requests through the same file; enable `mod_rewrite` and
`AllowOverride` and enable `pdo_pgsql` in that PHP installation. Apache hosting
has not been tested; the verified runtime is `npm start`.

The build type-checks the Function and Neon config, lints PHP, bundles the
Realtime browser client into `assets/neon.js`, and emits `build/function.mjs`.
The E2E suite uses the running app and deployed Neon services, creates temporary
test identities/reservations/files and cleans them up. It verifies HTTP access
controls, auth restoration/revocation, database persistence, attachment byte
equality and ownership, actual WebSocket delivery and query renewal.

## Deploy backend changes

```powershell
neon login
neon env pull
# Preserve ORIGIN_SECRET in .env.local. It is not a Neon-managed variable.
npm run build
neon deploy --env .env.local
```

The Function URL stays under the `hello` slug because deployed slugs are stable
identifiers. The display name is "Yogee reservation services". Function-specific
secrets are sent using `--env .env.local`; never commit this file.

Neon hosts the backend services. The PHP frontend is running locally; deployment
to a public PHP host and a custom domain are separate. For a public host, set
`APP_ORIGIN` to its HTTPS origin, add that origin using `neon neon-auth domain add`,
and add it to `preview.realtime.allowedOrigins` in `neon.ts`, then deploy with
`--update-existing` after reviewing the origin change. Serve through the router
and copy the environment securely to the PHP host.

## Existing data and admin access

No MySQL dump or database contents were included. The migration creates the
PostgreSQL schema and seeds the three packages/prices shown in the reservation
form. It does not transfer previous customers, passwords, reservations or admins.
No default admin/password is created. Existing admin authentication still uses
the separate hashed-password `admin_users` table in Postgres; customers use Neon
Auth. Admin customer deletion removes both profile and managed identity in a
single transaction, so that email can register again.

The supplied app also references missing `api/admin_inventory.php` and
`api/admin_reports.php`; those pre-existing pages are outside this Neon setup.
