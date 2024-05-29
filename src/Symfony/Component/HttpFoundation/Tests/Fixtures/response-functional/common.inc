<?php

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

error_reporting(-1);
ini_set('html_errors', 0);
ini_set('display_errors', 1);

if (filter_var(ini_get('xdebug.default_enable'), FILTER_VALIDATE_BOOL)) {
    xdebug_disable();
}

header_remove('X-Powered-By');
header('Content-Type: text/plain; charset=utf-8');

register_shutdown_function(function () {
    echo "\n";
    session_write_close();
    print_r(headers_list());
    echo "shutdown\n";
});
ob_start();

$r = new Response();
$r->headers->set('Date', 'Sat, 12 Nov 1955 20:04:00 GMT');

return $r;
