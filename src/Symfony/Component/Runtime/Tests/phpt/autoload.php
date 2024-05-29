<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Component\Runtime\SymfonyRuntime;

$_SERVER['APP_RUNTIME_OPTIONS'] ??= [];
$_SERVER['APP_RUNTIME_OPTIONS'] += [
    'project_dir' => __DIR__,
] + ($_SERVER['APP_RUNTIME_OPTIONS'] ?? []);

if (file_exists(dirname(__DIR__, 2).'/vendor/autoload.php')) {
    if (true === (require_once dirname(__DIR__, 2).'/vendor/autoload.php') || empty($_SERVER['SCRIPT_FILENAME'])) {
        return;
    }

    $app = require $_SERVER['SCRIPT_FILENAME'];
    $runtime = $_SERVER['APP_RUNTIME'] ?? SymfonyRuntime::class;
    $runtime = new $runtime($_SERVER['APP_RUNTIME_OPTIONS']);
    [$app, $args] = $runtime->getResolver($app)->resolve();
    exit($runtime->getRunner($app(...$args))->run());
}

if (!file_exists(dirname(__DIR__, 6).'/vendor/autoload_runtime.php')) {
    throw new LogicException('Autoloader not found.');
}

require dirname(__DIR__, 6).'/vendor/autoload_runtime.php';
