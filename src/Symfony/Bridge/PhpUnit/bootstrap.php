<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

if (!class_exists('PHPUnit_Util_ErrorHandler')) {
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

switch (getenv('SYMFONY_DEPRECATIONS_HELPER')) {
    case 'strict':
        DeprecationErrorHandler::register(true);
        break;

    case 'weak':
        error_reporting(error_reporting() & ~E_USER_DEPRECATED);
        // No break;
    default:
        DeprecationErrorHandler::register(false);
        break;
}
