<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * NativePHP's fresh command inherits Laravel's migrate:fresh signature, so its
 * real name drifts away from the one it is registered under. Symfony then
 * refuses to resolve it, which breaks `artisan list` and every typo suggestion.
 */
test('the native fresh command resolves under its registered name', function () {
    $command = app(Kernel::class)->all()['native:migrate:fresh'];

    expect($command->getName())->toBe('native:migrate:fresh')
        ->and($command->getDefinition()->hasOption('seed'))->toBeTrue();
});

test('artisan can list every command', function () {
    $this->artisan('list')->assertSuccessful();
});
