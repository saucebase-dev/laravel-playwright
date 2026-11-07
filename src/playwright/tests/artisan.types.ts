import type { Laravel } from '../src/index.ts';

declare const laravel: Laravel;

void laravel.artisan('db:seed --class=DatabaseSeeder');
void laravel.artisan('db:seed', { '--class': 'DatabaseSeeder', '--force': true });
void laravel.artisan('mail:send', { user: 1, '--id': [5, 13] });

// CLI token arrays are not Laravel's named parameter map.
// @ts-expect-error Use a command string or named parameters.
void laravel.artisan('db:seed', ['--class', 'DatabaseSeeder']);
