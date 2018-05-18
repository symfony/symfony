<?php

use Symfony\Component\HttpFoundation\Cookie;

$r = require __DIR__.'/common.inc';

try {
    $r->headers->setCookie(new Cookie('Hello + world', 'hodor'));
} catch (\InvalidArgumentException $e) {
    echo $e->getMessage();
}
