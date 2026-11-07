<?php

use Symfony\Component\HttpFoundation\Cookie;

$r = require __DIR__.'/common.inc';

$str = '?*():@&+$/%#[]';
$name = '!#$&*+-.^_`|~';

$r->headers->setCookie(new Cookie($name, $str, 0, '/', null, false, false, true, null));
$r->sendHeaders();

setrawcookie($name, $str, 0, '/', '', false, false);
