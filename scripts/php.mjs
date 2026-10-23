import { spawnSync, spawn } from 'node:child_process';
export const php = process.env.PHP_BIN || 'php';
export const phpArgs = ['-d', 'extension=pdo_pgsql', '-d', 'display_errors=0', '-d', 'upload_max_filesize=5M', '-d', 'post_max_size=6M'];
export function runPhp(args) {
  const result = spawnSync(php, [...phpArgs, ...args], { stdio: 'inherit' });
  if (result.error) throw result.error;
  if (result.status !== 0) process.exit(result.status ?? 1);
}
if (process.argv[2] === 'start') {
  const server = spawn(php, [...phpArgs, '-S', process.env.APP_LISTEN || '127.0.0.1:8080', 'router.php'], { stdio: 'inherit' });
  server.on('error', error => { console.error(error.message); process.exitCode = 1; });
  server.on('exit', code => process.exit(code ?? 0));
  for (const signal of ['SIGINT', 'SIGTERM']) process.on(signal, () => server.kill(signal));
} else if (process.argv[2]) runPhp(process.argv.slice(2));
