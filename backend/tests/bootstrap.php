<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

if ($_SERVER['APP_DEBUG']) {
    umask(0o000);
}

// Bring the test database up to date once per run (skip with SKIP_DB_SETUP=1, e.g. unit-only runs).
if (!filter_var($_SERVER['SKIP_DB_SETUP'] ?? $_ENV['SKIP_DB_SETUP'] ?? false, \FILTER_VALIDATE_BOOL)) {
    $console = escapeshellarg(dirname(__DIR__).'/bin/console');
    foreach ([
        'doctrine:database:create --if-not-exists --env=test -q',
        'doctrine:migrations:migrate --no-interaction --allow-no-migration --env=test -q',
    ] as $command) {
        passthru(sprintf('%s %s %s', escapeshellarg(\PHP_BINARY), $console, $command), $code);
        if (0 !== $code) {
            fwrite(\STDERR, "Test database setup failed ({$command}). Is MySQL running? Try `make up`.\n");
            exit(1);
        }
    }
}
