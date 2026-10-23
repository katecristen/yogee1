import { timingSafeEqual, randomUUID } from 'node:crypto';
import { Pool } from 'pg';
import { attachDatabasePool } from '@neon/functions';
import { createRemoteJWKSet, jwtVerify } from 'jose';
import { S3Client, PutObjectCommand, GetObjectCommand, DeleteObjectCommand } from '@aws-sdk/client-s3';
import { createRealtime, rawSql } from '@neon/realtime/server';

const databaseUrl = new URL(process.env.DATABASE_URL!);
databaseUrl.searchParams.set('sslmode', 'verify-full');
const pool = new Pool({ connectionString: databaseUrl.toString(), max: 5 });
attachDatabasePool(pool);
const jwks = createRemoteJWKSet(new URL(process.env.NEON_AUTH_JWKS_URL!));
const issuer = new URL(process.env.NEON_AUTH_BASE_URL!).origin;
const s3 = new S3Client({ forcePathStyle: true });
const realtime = createRealtime({ secret: process.env.NEON_REALTIME_SECRET!, db: new URL(process.env.DATABASE_URL!).pathname.slice(1) });
const expectedSecret = Buffer.from(process.env.ORIGIN_SECRET!);
const bucket = 'assets';
const maxBytes = 5 * 1024 * 1024;

async function boundedBody(request: Request) {
  const reader = request.body?.getReader();
  if (!reader) return Buffer.alloc(0);
  const chunks: Uint8Array[] = [];
  let size = 0;
  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;
    size += value.byteLength;
    if (size > maxBytes) { await reader.cancel(); throw new Error('TOO_LARGE'); }
    chunks.push(value);
  }
  return Buffer.concat(chunks);
}

export default {
  async fetch(request: Request): Promise<Response> {
    const secret = Buffer.from(request.headers.get('x-secret') ?? '');
    if (secret.length !== expectedSecret.length || !timingSafeEqual(secret, expectedSecret)) return Response.json({ message: 'Unauthorized' }, { status: 401 });
    const token = request.headers.get('authorization')?.replace(/^Bearer /i, '');
    let authId: string;
    try {
      const { payload } = await jwtVerify(token ?? '', jwks, { issuer });
      if (!payload.sub) throw new Error('No subject');
      authId = payload.sub;
    } catch { return Response.json({ message: 'Unauthorized' }, { status: 401 }); }
    const path = new URL(request.url).pathname.replace(/\/$/, '') || '/';
    try {
      const user = (await pool.query('SELECT user_id FROM users WHERE auth_id = $1 AND is_active = 1', [authId])).rows[0];
      if (!user) return Response.json({ message: 'Unauthorized' }, { status: 401 });
      if (path === '/live' && request.method === 'GET') {
        const query = rawSql('SELECT r.reservation_id, r.status, r.payment_status, r.updated_at FROM reservations r INNER JOIN users u ON u.user_id = r.user_id WHERE u.auth_id = $1 AND u.is_active = 1 ORDER BY r.reservation_id', [authId]);
        return Response.json({ sealedQuery: await realtime.seal({ query }), userId: user.user_id });
      }
      const match = path.match(/^\/reservations\/(\d+)\/attachments$/);
      if (match && ['GET', 'POST'].includes(request.method)) {
        const reservationId = Number(match[1]);
        const owner = (await pool.query('SELECT 1 FROM reservations WHERE reservation_id = $1 AND user_id = $2', [reservationId, user.user_id])).rowCount;
        if (!owner) return Response.json({ message: 'Reservation not found' }, { status: 404 });
        if (request.method === 'GET') {
          const { rows } = await pool.query('SELECT attachment_id, file_name, content_type FROM reservation_attachments WHERE reservation_id = $1 ORDER BY created_at', [reservationId]);
          return Response.json({ attachments: rows });
        }
        if (Number(request.headers.get('content-length')) > maxBytes) return Response.json({ message: 'Maximum file size is 5 MB' }, { status: 413 });
        const body = await boundedBody(request);
        const contentType = request.headers.get('content-type') ?? '';
        const valid = (contentType === 'application/pdf' && body.subarray(0, 5).toString() === '%PDF-') ||
          (contentType === 'image/png' && body.subarray(0, 8).equals(Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]))) ||
          (contentType === 'image/jpeg' && body[0] === 255 && body[1] === 216 && body[2] === 255);
        if (!valid) return Response.json({ message: 'Upload a PNG, JPEG, or PDF file' }, { status: 415 });
        const id = randomUUID();
        const key = `reservations/${user.user_id}/${reservationId}/${id}`;
        const name = decodeURIComponent(request.headers.get('x-file-name') ?? 'attachment').replace(/[\r\n\x00-\x1f]/g, '').slice(0, 200);
        const connection = await pool.connect();
        let uploaded = false;
        try {
          await connection.query('BEGIN');
          // Serialize uploads per reservation so simultaneous requests cannot
          // both claim the last attachment slot. Keep ownership in the lock.
          const locked = await connection.query('SELECT 1 FROM reservations WHERE reservation_id=$1 AND user_id=$2 FOR UPDATE', [reservationId, user.user_id]);
          if (!locked.rowCount) { await connection.query('ROLLBACK'); return Response.json({ message: 'Reservation not found' }, { status: 404 }); }
          const count = (await connection.query('SELECT count(*)::int AS n FROM reservation_attachments WHERE reservation_id=$1', [reservationId])).rows[0].n;
          if (count >= 10) { await connection.query('ROLLBACK'); return Response.json({ message: 'Maximum 10 attachments per reservation' }, { status: 409 }); }
          await s3.send(new PutObjectCommand({ Bucket: bucket, Key: key, Body: body, ContentType: contentType }));
          uploaded = true;
          await connection.query('INSERT INTO reservation_attachments (attachment_id, reservation_id, object_key, file_name, content_type) VALUES ($1,$2,$3,$4,$5)', [id, reservationId, key, name, contentType]);
          await connection.query('COMMIT');
        } catch (error) {
          await connection.query('ROLLBACK');
          if (uploaded) await s3.send(new DeleteObjectCommand({ Bucket: bucket, Key: key }));
          throw error;
        } finally { connection.release(); }
        return Response.json({ attachment_id: id, file_name: name }, { status: 201 });
      }
      const attachment = path.match(/^\/attachments\/([a-f0-9-]{36})$/);
      if (attachment && ['GET', 'DELETE'].includes(request.method)) {
        const file = (await pool.query('SELECT a.* FROM reservation_attachments a JOIN reservations r ON r.reservation_id = a.reservation_id WHERE a.attachment_id = $1 AND r.user_id = $2', [attachment[1], user.user_id])).rows[0];
        if (!file) return Response.json({ message: 'Attachment not found' }, { status: 404 });
        if (request.method === 'DELETE') {
          await s3.send(new DeleteObjectCommand({ Bucket: bucket, Key: file.object_key }));
          await pool.query('DELETE FROM reservation_attachments WHERE attachment_id = $1', [file.attachment_id]);
          return Response.json({ success: true });
        }
        const object = await s3.send(new GetObjectCommand({ Bucket: bucket, Key: file.object_key }));
        const bytes = new Uint8Array(await object.Body!.transformToByteArray());
        return new Response(bytes.buffer, { headers: { 'Content-Type': file.content_type, 'Content-Disposition': `attachment; filename*=UTF-8''${encodeURIComponent(file.file_name)}`, 'Cache-Control': 'no-store', 'X-Content-Type-Options': 'nosniff' } });
      }
      return Response.json({ message: 'Not found' }, { status: 404 });
    } catch (error) {
      if (error instanceof Error && error.message === 'TOO_LARGE') return Response.json({ message: 'Maximum file size is 5 MB' }, { status: 413 });
      console.error('Reservation service failed', error instanceof Error ? error.name : 'Error');
      return Response.json({ message: 'Reservation service unavailable' }, { status: 500 });
    }
  }
};
