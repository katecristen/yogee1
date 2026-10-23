import assert from 'node:assert/strict';
import { randomUUID } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { php } from './php.mjs';
import { Pool } from 'pg';
import { createRealtimeClient } from '@neon/realtime/client';

process.loadEnvFile('.env.local');
const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:8080';
const databaseUrl = new URL(process.env.DATABASE_URL); databaseUrl.searchParams.set('sslmode', 'verify-full');
const pool = new Pool({ connectionString: databaseUrl.toString(), max: 2 });
const accounts = [];
const adminUsername = 'e2e-' + randomUUID();
let attachmentId, reservationId, client, subscription, renewalTimer, renewalPromise;
let assertions = 0;
const check = (condition, message) => { assert.ok(condition, message); assertions++; };

class BrowserSession {
  cookie = '';
  async request(path, init = {}) {
    const response = await fetch(base + path, { ...init, headers: { Origin: base, Cookie: this.cookie, ...init.headers }, signal: AbortSignal.timeout(90000) });
    const cookie = response.headers.getSetCookie().find(value => value.startsWith('PHPSESSID='));
    if (cookie) this.cookie = cookie.split(';')[0];
    return response;
  }
  async json(path, body, expected = 200) {
    const response = await this.request(path, body === undefined ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const result = await response.json();
    assert.equal(response.status, expected, `${path}: ${result.message ?? response.status}`);
    return result;
  }
}

const user = new BrowserSession(), other = new BrowserSession(), anonymous = new BrowserSession();
try {
  for (const path of ['/.env.local', '/config/env.php', '/scripts/migrate.php', '/create_test_admin.php', '/node_modules/pg/package.json']) {
    check((await anonymous.request(path)).status === 404, 'Private source blocked: ' + path);
  }
  check((await anonymous.request('/api/neon.php?action=live')).status === 401, 'Anonymous protected route denied');
  check((await fetch(process.env.NEON_FUNCTION_HELLO_BASE_URL)).status === 401, 'Native Function rejects anonymous requests');
  check((await fetch(process.env.NEON_FUNCTION_HELLO_BASE_URL, { headers: { 'X-Secret': process.env.ORIGIN_SECRET, Authorization: 'Bearer invalid' } })).status === 401, 'Function rejects invalid JWT');
  console.log('PASS access controls: private files, app API, Function secret and JWT');

  const password = randomUUID() + '-Test!';
  const invalidEmail = `yogee-invalid-${randomUUID()}@example.com`;
  await anonymous.json('/api/register.php', { firstName: 'Invalid', lastName: 'Date', email: invalidEmail, password, confirmPassword: password, dateOfBirth: '1995-02-31' }, 400);
  check((await pool.query('SELECT 1 FROM neon_auth."user" WHERE email=$1', [invalidEmail])).rows.length === 0, 'Invalid DOB rejected before creating an Auth identity');
  for (const browser of [user, other]) {
    const email = `yogee-e2e-${randomUUID()}@example.com`;
    accounts.push(email);
    const registered = await browser.json('/api/register.php', { firstName: 'Neon', lastName: 'E2E', email, phone: '09123456789', dateOfBirth: '1995-01-01', password, confirmPassword: password });
    check(registered.success, 'Sign-up succeeded');
    const loggedIn = await browser.json('/api/login.php', { email, password });
    check(loggedIn.success && loggedIn.user.user_id, 'Sign-in succeeded');
    const restored = new BrowserSession(); restored.cookie = browser.cookie;
    const session = await restored.json('/api/check_session.php');
    check(session.user?.email === email, 'Session restored in a new client');
  }
  await anonymous.json('/api/login.php', { email: accounts[0], password: 'Wrong-password!' }, 401);
  await pool.query('UPDATE users SET is_active=0 WHERE email=$1', [accounts[1]]);
  await user.json('/api/login.php', { email: accounts[1], password }, 401);
  check((await user.json('/api/check_session.php')).user === null, 'Failed identity switch clears prior local identity');
  await pool.query('UPDATE users SET is_active=1 WHERE email=$1', [accounts[1]]);
  await user.json('/api/login.php', { email: accounts[0], password });
  await pool.query('DELETE FROM users WHERE email=$1', [accounts[1]]);
  await other.json('/api/login.php', { email: accounts[1], password });
  check((await other.json('/api/check_session.php')).user.email === accounts[1], 'Verified sign-in recovers a missing profile');
  console.log('PASS Neon Auth: sign-up, sign-in, invalid password, session restoration');

  const packages = await user.json('/api/reservation.php?action=packages');
  check(packages.packages.length >= 3, 'Packages read from Postgres');
  const event = new Date(Date.now() + 10 * 86400000).toISOString().slice(0, 16);
  const created = await user.json('/api/reservation.php', { action: 'create', customer_name: 'Neon E2E', contact_number: '09123456789', email: accounts[0], event_datetime: event, package_id: packages.packages[0].package_id, additional_notes: 'Automated Neon integration test' });
  check(created.success, 'Reservation created'); reservationId = created.reservation_id;
  const saved = await pool.query('SELECT * FROM reservations WHERE reservation_id = $1', [reservationId]);
  check(saved.rows[0]?.email === accounts[0], 'Reservation persisted in the deployed database');
  check(Number(saved.rows[0].total_amount) === Number(packages.packages[0].price) + 2000, 'Server calculated reservation amount');
  await user.json('/api/user_update.php', { first_name: 'Neon', last_name: 'Updated', phone: '09123456789', date_of_birth: '1995-01-01' });
  check((await user.json('/api/check_session.php')).user.last_name === 'Updated', 'Profile update persisted');
  console.log('PASS Postgres: package reads, reservation insert/read, server pricing, profile update');

  const live = await user.json('/api/neon.php?action=live');
  client = createRealtimeClient({ url: live.realtimeUrl });
  subscription = client.subscribe(live.sealedQuery);
  // Capabilities expire after 60s; storage tests can exceed that duration.
  // Renew just as the app does rather than testing an expired capability.
  let renewalError;
  renewalTimer = setInterval(() => {
    renewalPromise = user.json('/api/neon.php?action=live')
      .then(next => subscription.renew(next.sealedQuery))
      .catch(error => { renewalError = error; });
  }, 30000);
  const waitFor = predicate => new Promise((resolve, reject) => {
    let off = () => {};
    const timer = setTimeout(() => { off(); reject(new Error('Realtime update timed out')); }, 45000);
    const inspect = snapshot => {
      if (snapshot.status === 'error') { clearTimeout(timer); off(); reject(snapshot.error); }
      else if (snapshot.data && predicate(snapshot.data)) { clearTimeout(timer); off(); resolve(snapshot.data); }
    };
    off = subscription.onChange(inspect);
    inspect(subscription.getSnapshot());
  });
  const initial = await waitFor(rows => rows.some(row => String(row.reservation_id) === String(reservationId)));
  check(initial.length > 0, 'Realtime initial result received');
  const otherRows = await other.json('/api/reservation.php?action=user_reservations');
  check(otherRows.reservations.length === 0, 'Reservations isolated between accounts');

  const pdf = Buffer.from('%PDF-1.4\n%Yogee integration test\n1 0 obj<</Type/Catalog>>endobj\n%%EOF\n');
  const data = new FormData(); data.append('file', new Blob([pdf], { type: 'application/pdf' }), 'receipt.pdf');
  const upload = await user.request(`/api/neon.php?action=attachments&reservation_id=${reservationId}`, { method: 'POST', body: data });
  const uploaded = await upload.json();
  check(upload.status === 201, `Attachment upload: ${uploaded.message ?? upload.status}`);
  attachmentId = uploaded.attachment_id;
  const listed = await user.json(`/api/neon.php?action=attachments&reservation_id=${reservationId}`);
  check(listed.attachments.some(file => file.attachment_id === attachmentId), 'Uploaded attachment listed');
  const downloaded = await user.request(`/api/neon.php?action=download&attachment_id=${attachmentId}`);
  check(downloaded.status === 200 && Buffer.from(await downloaded.arrayBuffer()).equals(pdf), 'Downloaded bytes match uploaded bytes');
  check((await other.request(`/api/neon.php?action=download&attachment_id=${attachmentId}`)).status === 404, 'Other account cannot download attachment');
  check((await other.request(`/api/neon.php?action=attachments&reservation_id=${reservationId}`)).status === 404, 'Other account cannot list attachments');
  const bad = new FormData(); bad.append('file', new Blob(['<script>bad</script>'], { type: 'text/html' }), 'bad.html');
  check((await user.request(`/api/neon.php?action=attachments&reservation_id=${reservationId}`, { method: 'POST', body: bad })).status === 415, 'Unsupported attachment rejected');
  await user.json(`/api/neon.php?action=delete_attachment&attachment_id=${attachmentId}`, {});
  check((await user.request(`/api/neon.php?action=download&attachment_id=${attachmentId}`)).status === 404, 'Deleted attachment unavailable');
  attachmentId = undefined;
  console.log('PASS Object Storage + Functions: upload/list/download/delete, byte equality, ownership and file validation');

  check((await other.json('/api/reservation.php', { action: 'cancel', reservation_id: reservationId }, 400)).success === false, 'Other user cannot cancel reservation');
  const update = waitFor(rows => rows.some(row => String(row.reservation_id) === String(reservationId) && row.status === 'cancelled'));
  await user.json('/api/reservation.php', { action: 'cancel', reservation_id: reservationId });
  await update;
  check(true, 'Realtime delivered committed cancellation over WebSocket');
  const renewed = await user.json('/api/neon.php?action=live');
  await subscription.renew(renewed.sealedQuery);
  clearInterval(renewalTimer);
  await renewalPromise;
  if (renewalError) throw renewalError;
  console.log('PASS Realtime: initial rows, committed reservation change over WebSocket, sealed query renewal');

  await user.json('/api/logout.php', {});
  check((await user.json('/api/check_session.php')).user === null, 'Signed-out session not restored');
  check((await user.request('/api/neon.php?action=live')).status === 401, 'Protected API denied after sign-out');
  console.log('PASS Neon Auth: sign-out and protected access denied');

  const hash = spawnSync(php, ['-r', 'echo password_hash($argv[1], PASSWORD_DEFAULT);', password], { encoding: 'utf8' });
  check(hash.status === 0 && hash.stdout.startsWith('$'), 'Admin test password hashed');
  await pool.query('INSERT INTO admin_users (username,email,password_hash,full_name) VALUES ($1,$2,$3,$4)', [adminUsername, adminUsername + '@example.com', hash.stdout, 'E2E Admin']);
  const admin = new BrowserSession();
  await admin.json('/api/admin_login.php', { username: adminUsername, password });
  const deletedUser = (await other.json('/api/check_session.php')).user;
  const removed = await admin.request('/api/admin_users.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ user_id: deletedUser.user_id }) });
  check((await removed.json()).success, 'Admin deleted customer');
  check((await pool.query('SELECT 1 FROM neon_auth."user" WHERE email=$1', [accounts[1]])).rows.length === 0, 'Admin deletion also removed managed identity');
  await other.json('/api/register.php', { firstName: 'Neon', lastName: 'Recreated', email: accounts[1], password, confirmPassword: password });
  check((await other.json('/api/login.php', { email: accounts[1], password })).success, 'Deleted email can register and sign in again');
  console.log('PASS account consistency: invalid DOB, missing-profile recovery, failed identity switch, admin deletion and re-registration');
  console.log(`PASS ${assertions} end-to-end assertions across all five services`);
} finally {
  clearInterval(renewalTimer);
  await renewalPromise;
  subscription?.unsubscribe(); client?.close();
  if (attachmentId) { try { await user.json(`/api/neon.php?action=delete_attachment&attachment_id=${attachmentId}`, {}); } catch {} }
  for (const email of accounts) {
    const { rows } = await pool.query('SELECT user_id, auth_id FROM users WHERE email = $1', [email]);
    if (rows[0]) {
      await pool.query('DELETE FROM reservations WHERE user_id = $1', [rows[0].user_id]);
      await pool.query('DELETE FROM users WHERE user_id = $1', [rows[0].user_id]);
      await pool.query('DELETE FROM neon_auth."user" WHERE id = $1', [rows[0].auth_id]);
    } else {
      await pool.query('DELETE FROM neon_auth."user" WHERE email = $1', [email]);
    }
  }
  await pool.query('DELETE FROM admin_users WHERE username=$1', [adminUsername]);
  await pool.end();
  console.log('Test accounts and reservations cleaned up.');
}
// Realtime's WebSocket implementation can retain idle timers after close.
// Only exit after every assertion and awaited cleanup has completed.
process.exit(0);
