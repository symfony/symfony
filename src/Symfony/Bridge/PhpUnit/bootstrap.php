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
use Doctrine\Deprecations\Deprecation;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

// Detect if we need to serialize deprecations to a file.
if (in_array(\PHP_SAPI, ['cli', 'phpdbg'], true) && $file = getenv('SYMFONY_DEPRECATIONS_SERIALIZE')) {
    DeprecationErrorHandler::collectDeprecations($file);

    return;
}

// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists(\PHPUnit\TextUI\Command::class, false)) {
    return;
}

if (isset($fileIdentifier)) {
    unset($GLOBALS['__composer_autoload_files'][$fileIdentifier]);
}

// Enforce a consistent locale
setlocale(\LC_ALL, 'C');

if (class_exists(Deprecation::class)) {
    Deprecation::withoutDeduplication();

    if (\PHP_VERSION_ID < 80000) {
        // Ignore deprecations about the annotation mapping driver when it's not possible to move to the attribute driver yet
        Deprecation::ignoreDeprecations('https://github.com/doctrine/orm/issues/10098');
    }
}

if (!class_exists(AnnotationRegistry::class, false) && class_exists(AnnotationRegistry::class)) {
    if (method_exists(AnnotationRegistry::class, 'registerUniqueLoader')) {
        AnnotationRegistry::registerUniqueLoader('class_exists');
    } elseif (method_exists(AnnotationRegistry::class, 'registerLoader')) {
        AnnotationRegistry::registerLoader('class_exists');
    }
}

if ('disabled' !== getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
}
