<?php

use Symfony\Component\HttpFoundation\Cookie;

$r = require __DIR__.'/common.inc';

$str1 = "=,; \t\r\n\v\f";
$r->headers->setCookie(new Cookie($str1, $str1, 0, '', null, false, false, false, null));

$str2 = '?*():@&+$/%#[]';

$r->headers->setCookie(new Cookie($str2, $str2, 0, '', null, false, false, false, null));
$r->sendHeaders();

setcookie($str2, $str2, 0, '/');
