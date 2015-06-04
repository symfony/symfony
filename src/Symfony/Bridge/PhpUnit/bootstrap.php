<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

// Detect if we're loaded by an actual run of phpunit
if (!defined('PHPUNIT_COMPOSER_INSTALL') && !class_exists('PHPUnit_TextUI_Command', false)) {
    return;
}

if (PHP_VERSION_ID >= 50400 && gc_enabled()) {
    // Disabling Zend Garbage Collection to prevent segfaults with PHP5.4+
    // https://bugs.php.net/bug.php?id=53976
    gc_disable();
}

if (class_exists('Doctrine\Common\Annotations\AnnotationRegistry')) {
    AnnotationRegistry::registerLoader('class_exists');
}

DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
