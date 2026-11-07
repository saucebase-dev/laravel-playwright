import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

test('artisan accepts command strings and named parameters, not CLI token arrays', () => {
    execFileSync(process.execPath, [
        fileURLToPath(new URL('../../../node_modules/typescript/bin/tsc', import.meta.url)),
        '--noEmit', '--strict', '--skipLibCheck', '--moduleResolution', 'bundler',
        '--module', 'esnext', '--target', 'es2020', '--allowImportingTsExtensions',
        fileURLToPath(new URL('./artisan.types.ts', import.meta.url)),
    ], { stdio: 'pipe' });
});
