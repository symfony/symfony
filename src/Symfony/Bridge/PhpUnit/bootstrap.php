<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Deprecations\Deprecation;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

// Skip if we're using PHPUnit >=10
if (class_exists(PHPUnit\Metadata\Metadata::class)) {
    return;
}

// Detect if we need to serialize deprecations to a file.
if (in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && $file = getenv('SYMFONY_DEPRECATIONS_SERIALIZE')) {
    DeprecationErrorHandler::collectDeprecations($file);

    return;
}

// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists(PHPUnit\TextUI\Command::class, false)) {
    return;
}

if (isset($fileIdentifier)) {
    unset($GLOBALS['__composer_autoload_files'][$fileIdentifier]);
}

if (class_exists(Deprecation::class)) {
    Deprecation::withoutDeduplication();
}

if ('disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
}
