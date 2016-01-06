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

// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists('PHPUnit_TextUI_Command', false)) {
    return;
}

// Enforce a consistent locale
setlocale(LC_ALL, 'C');

if (class_exists('Doctrine\Common\Annotations\AnnotationRegistry')) {
    AnnotationRegistry::registerLoader('class_exists');
}

DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
