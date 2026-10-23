import { readdirSync, mkdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { build } from 'esbuild';
import { php, phpArgs } from './php.mjs';
const result = spawnSync(process.execPath, ['node_modules/typescript/bin/tsc', '--noEmit', '--module', 'nodenext', '--moduleResolution', 'nodenext', '--target', 'es2022', '--lib', 'es2022,dom', '--esModuleInterop', '--skipLibCheck', 'hello.ts', 'neon.ts'], { stdio: 'inherit' });
if (result.status !== 0) process.exit(result.status ?? 1);
let count = 0;
for (const directory of ['.', 'api', 'classes', 'config', 'includes', 'scripts']) {
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    if (!entry.isFile() || !entry.name.endsWith('.php')) continue;
    const checked = spawnSync(php, [...phpArgs, '-l', `${directory}/${entry.name}`], { encoding: 'utf8' });
    if (checked.status !== 0) { console.error(checked.stdout, checked.stderr); process.exit(1); }
    count++;
  }
}
mkdirSync('assets', { recursive: true }); mkdirSync('build', { recursive: true });
await build({ entryPoints: ['client/neon.js'], bundle: true, format: 'esm', platform: 'browser', target: 'es2022', minify: true, outfile: 'assets/neon.js' });
await build({ entryPoints: ['hello.ts'], bundle: true, packages: 'external', format: 'esm', platform: 'node', target: 'node24', outfile: 'build/function.mjs' });
console.log(`PASS build: TypeScript checked, ${count} PHP files linted, browser and Function bundles generated.`);
