<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpFoundation\Request;

if (file_exists(__DIR__.'/../../../../../../../../vendor/autoload.php')) {
    require __DIR__.'/../../../../../../../../vendor/autoload.php';
} else {
    require __DIR__.'/../../../../vendor/autoload.php';
}

require __DIR__.'/../Kernel.php';

$app = new Kernel($_SERVER['APP_ENV'] ?? 'dev', $_SERVER['APP_DEBUG'] ?? true);

if (\PHP_SAPI === 'cli') {
    $application = new Application($app);
    exit($application->run());
}

$request = Request::createFromGlobals();
$response = $app->handle($request);
$response->send();
$app->terminate($request, $response);
