<?php

// Requires FrankenPHP

use Symfony\Component\HttpFoundation\Response;

$parent = __DIR__;
while (!@file_exists($parent.'/vendor/autoload.php')) {
    if (!@file_exists($parent)) {
        // open_basedir restriction in effect
        break;
    }
    if ($parent === dirname($parent)) {
        echo "vendor/autoload.php not found\n";
        exit(1);
    }

    $parent = dirname($parent);
}

require $parent.'/vendor/autoload.php';

$r = new Response();
$r->headers->set('Link', '</css/style.css>; rel="preload"; as="style"');
$r->sendHeaders(103);

$r->headers->set('Link', '</js/app.js>; rel="preload"; as="script"', false);
$r->sendHeaders(103);

$r->setContent('Hello, Early Hints');
$r->send();
