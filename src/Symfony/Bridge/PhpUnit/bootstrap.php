<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

// Detect if we need to serialize deprecations to a file.
if ($file = getenv('SYMFONY_DEPRECATIONS_SERIALIZE')) {
    DeprecationErrorHandler::collectDeprecations($file);

    return;
}

// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists('PHPUnit_TextUI_Command', false) && !class_exists('PHPUnit\TextUI\Command', false)) {
    return;
}

// Enforce a consistent locale
setlocale(LC_ALL, 'C');

if (!class_exists('Doctrine\Common\Annotations\AnnotationRegistry', false) && class_exists('Doctrine\Common\Annotations\AnnotationRegistry')) {
    if (method_exists('Doctrine\Common\Annotations\AnnotationRegistry', 'registerUniqueLoader')) {
        AnnotationRegistry::registerUniqueLoader('class_exists');
    } else {
        AnnotationRegistry::registerLoader('class_exists');
    }
}

// load an .env.test file for override phpunit.xml(.dist) vars
$path = dirname(getenv('SYMFONY_PHPUNIT_DIR'), 2);
if (file_exists($path.'/vendor/autoload.php')) {
    $loader = clone require $path.'/vendor/autoload.php';
    if (!class_exists(\Symfony\Component\Dotenv\Dotenv::class)) {
        throw new \RuntimeException('Please run "composer require symfony/dotenv" to load the ".env" files configuring the application.');
    }

    $dotenv = new \Symfony\Component\Dotenv\Dotenv();
    $path .= '/.env.'.(false !== getenv('APP_ENV') ? getenv('APP_ENV') : 'test');
    if (file_exists($p = $path)) {
        $dotenv->load($p);
    }

    $loader->unregister();
}

if ('disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
}
