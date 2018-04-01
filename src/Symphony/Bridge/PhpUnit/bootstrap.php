<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symphony\Bridge\PhpUnit\DeprecationErrorHandler;

// Detect if we need to serialize deprecations to a file.
if ($file = getenv('SYMPHONY_DEPRECATIONS_SERIALIZE')) {
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

if ('disabled' !== getenv('SYMPHONY_DEPRECATIONS_HELPER')) {
    DeprecationErrorHandler::register(getenv('SYMPHONY_DEPRECATIONS_HELPER'));
}
