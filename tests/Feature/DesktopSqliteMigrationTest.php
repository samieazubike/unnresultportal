<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

/**
 * NativePHP migrates the desktop shell's local SQLite store on startup —
 * `migrate --force` in a packaged build, `native:migrate` in development when
 * the file is missing. The schema is unused there (the app renders the web
 * application over HTTP), but the migration run still has to complete, so
 * nothing in database/migrations may be PostgreSQL-only without a guard.
 */
test('the full migration set runs on sqlite', function () {
    $path = storage_path('framework/testing/desktop-migration-'.uniqid().'.sqlite');

    touch($path);

    config(['database.connections.desktop_sqlite' => [
        'driver' => 'sqlite',
        'database' => $path,
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    try {
        $exitCode = Artisan::call('migrate', [
            '--database' => 'desktop_sqlite',
            '--force' => true,
        ]);

        expect($exitCode)->toBe(0);

        $schema = Schema::connection('desktop_sqlite');

        // The two tables carrying the PostgreSQL-only column and constraint
        // definitions, plus the framework tables NativePHP actually uses.
        expect($schema->hasTable('results'))->toBeTrue()
            ->and($schema->hasTable('recommendations'))->toBeTrue()
            ->and($schema->hasTable('result_audits'))->toBeTrue()
            ->and($schema->hasTable('course_lecturer'))->toBeTrue()
            ->and($schema->hasTable('jobs'))->toBeTrue()
            ->and($schema->hasTable('cache'))->toBeTrue();
    } finally {
        @unlink($path);
    }
});
