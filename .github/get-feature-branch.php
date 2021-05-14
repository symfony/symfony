#!/usr/bin/env php
<?php

error_reporting(E_ALL);
set_error_handler(static function(int $errno, string $errstr, string $errfile = null, int $errline = null) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$versions = json_decode(
    file_get_contents('https://flex.symfony.com/versions.json'),
    true
);

if (json_last_error() !== JSON_ERROR_NONE) {
    throw new RuntimeException(json_last_error_msg());
}
if (!isset($versions['dev-name'])) {
    throw new RuntimeException('Key not found: dev-name');
}

echo $versions['dev-name'] . "\n";
