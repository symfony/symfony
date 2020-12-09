<?php

$phar = new Phar(__DIR__.\DIRECTORY_SEPARATOR.'deprecation.phar', 0, 'deprecation.phar');
$phar->buildFromDirectory(__DIR__.\DIRECTORY_SEPARATOR.'deprecation');
