<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$environmentPath = __DIR__.'/../.env';

if (! file_exists($environmentPath)) {
    copy(__DIR__.'/../.env.example', $environmentPath);
}

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (! $app->environment('local')) {
    fwrite(STDERR, "composer setup je namijenjen samo lokalnom okruzenju.\n");
    exit(1);
}

if (! config('app.key')) {
    $kernel->call('key:generate', ['--no-interaction' => true]);
}

if (config('database.default') === 'sqlite') {
    $database = config('database.connections.sqlite.database');

    if (! file_exists($database)) {
        touch($database);
    }
}
