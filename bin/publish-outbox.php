<?php

declare(strict_types=1);

use App\Shared\Infrastructure\Event\OutboxRelay;
use DI\ContainerBuilder;

require __DIR__ . '/../vendor/autoload.php';

$containerBuilder = new ContainerBuilder();
(require __DIR__ . '/../app/settings.php')($containerBuilder);
(require __DIR__ . '/../app/dependencies.php')($containerBuilder);
(require __DIR__ . '/../app/repositories.php')($containerBuilder);

/** @var OutboxRelay $relay */
$relay = $containerBuilder->build()->get(OutboxRelay::class);
$follow = in_array('--follow', $argv, true);

do {
    $published = $relay->publishPending();

    if (!$follow) {
        fwrite(STDOUT, $published . PHP_EOL);
        break;
    }

    if ($published === 0) {
        usleep(200000);
    }
} while (true);
