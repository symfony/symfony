<?php

use Symfony\Component\HttpFoundation\Cookie;

$r = require __DIR__.'/common.inc';

$str = '?*():@&+$/%#[]';

$r->headers->setCookie(new Cookie($str, $str, 0, '/', null, false, false, true, null));
$r->sendHeaders();

setrawcookie($str, $str, 0, '/', '', false, false);
