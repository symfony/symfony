<?php

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler;

if (!class_exists('PHPUnit_Util_ErrorHandler')) {
    return;
}

// Disabling Zend Garbage Collection to prevent segfaults with PHP5.4+
// https://bugs.php.net/bug.php?id=53976
gc_disable();

if (class_exists('Doctrine\Common\Annotations\AnnotationRegistry')) {
    AnnotationRegistry::registerLoader('class_exists');
}

DeprecationErrorHandler::register(getenv('SYMFONY_DEPRECATIONS_HELPER'));
