import { createRealtimeClient } from '@neon/realtime/client';

const status = document.getElementById('live-status');
const form = document.getElementById('attachment-form');
const files = document.getElementById('attachment-list');
const message = document.getElementById('attachment-status');
let client, subscription, renewal;
let sessionUserId;
let leaving = false;

async function api(url, options) {
  const response = await fetch(url, options);
  const result = await response.json();
  if (response.status === 401) { if (!leaving) window.location.assign('Login.html'); throw new Error('Please sign in again.'); }
  if (!response.ok) throw new Error(result.message ?? 'Request failed.');
  return result;
}

function populateReservations(rows) {
  const select = document.getElementById('attachment-reservation');
  const selected = select.value;
  select.replaceChildren(...rows.map(row => {
    const option = document.createElement('option');
    option.value = row.reservation_id;
    option.textContent = `Reservation #${row.reservation_id} — ${row.status}`;
    return option;
  }));
  if (rows.some(row => String(row.reservation_id) === selected)) select.value = selected;
  form.querySelector('button').disabled = rows.length === 0;
}

async function listAttachments() {
  files.replaceChildren();
  const id = document.getElementById('attachment-reservation').value;
  if (!id) return;
  const result = await api(`api/neon.php?action=attachments&reservation_id=${id}`);
  for (const file of result.attachments) {
    const item = document.createElement('li');
    const link = document.createElement('a');
    link.href = `api/neon.php?action=download&attachment_id=${file.attachment_id}`;
    link.textContent = file.file_name;
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.textContent = 'Remove';
    remove.addEventListener('click', async () => {
      try { await api(`api/neon.php?action=delete_attachment&attachment_id=${file.attachment_id}`, { method: 'POST' }); await listAttachments(); }
      catch (error) { message.textContent = error.message; }
    });
    item.append(link, ' ', remove);
    files.append(item);
  }
}

async function seal() {
  const result = await api('api/neon.php?action=live');
  if (sessionUserId !== undefined && result.userId !== sessionUserId) { window.location.reload(); throw new Error('Account changed. Reloading.'); }
  sessionUserId = result.userId;
  return result;
}

function scheduleRenewal(query) {
  clearTimeout(renewal);
  if (leaving) return;
  renewal = setTimeout(async () => {
    try {
      const { sealedQuery } = await seal();
      await subscription.renew(sealedQuery);
      scheduleRenewal(sealedQuery);
    } catch (error) { status.textContent = `Live updates stopped: ${error.message} Reload to reconnect.`; client?.close(); }
  }, Math.max(1000, query.expiresAt - Date.now() - 10000));
}

async function start() {
  try {
    const config = await seal();
    client = createRealtimeClient({ url: config.realtimeUrl });
    subscription = client.subscribe(config.sealedQuery);
    let previous;
    subscription.onChange(snapshot => {
      if (leaving) return;
      status.textContent = snapshot.status === 'live' ? 'Reservations are up to date.' : snapshot.status === 'error' ? 'Live updates stopped. Reload to reconnect.' : 'Connecting live updates…';
      if (!snapshot.data) return;
      const signature = JSON.stringify(snapshot.data);
      if (signature !== previous) {
        previous = signature;
        populateReservations(snapshot.data);
        window.loadReservations?.();
        listAttachments().catch(error => { message.textContent = error.message; });
      }
    });
    scheduleRenewal(config.sealedQuery);
  } catch (error) { status.textContent = `Live updates unavailable: ${error.message}`; }
}

form.addEventListener('submit', async event => {
  event.preventDefault();
  const file = document.getElementById('attachment-file').files[0];
  if (!file || file.size > 5 * 1024 * 1024) { message.textContent = 'Choose a PNG, JPEG, or PDF up to 5 MB.'; return; }
  const button = form.querySelector('button');
  button.disabled = true;
  message.textContent = 'Uploading…';
  try {
    const session = await api('api/check_session.php');
    if (!session.user || session.user.user_id !== sessionUserId) { window.location.reload(); return; }
    const data = new FormData(); data.append('file', file);
    await api(`api/neon.php?action=attachments&reservation_id=${document.getElementById('attachment-reservation').value}`, { method: 'POST', body: data });
    document.getElementById('attachment-file').value = '';
    message.textContent = 'Attachment uploaded.';
    await listAttachments();
  } catch (error) { message.textContent = error.message; }
  finally { button.disabled = false; }
});
document.getElementById('attachment-reservation').addEventListener('change', () => listAttachments().catch(error => { message.textContent = error.message; }));
// A download can fire beforeunload while keeping this page open. Only close
// the live connection when the page actually leaves.
window.addEventListener('pagehide', () => { clearTimeout(renewal); subscription?.unsubscribe(); client?.close(); });
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
// Stop before the inline logout handler revokes the session, so an in-flight
// renewal cannot race the intended navigation with its own sign-in redirect.
document.addEventListener('click', event => {
  if (event.target instanceof Element && event.target.closest('.logout-btn')) {
    leaving = true;
    clearTimeout(renewal); subscription?.unsubscribe(); client?.close();
  }
}, { capture: true });
start();
