<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$_SERVER['APP_RUNTIME'] = \Symfony\Component\Runtime\PsrRuntime::class;

require __DIR__.'/autoload.php';

return function (\Psr\Http\Message\ServerRequestInterface $request) {
    return new \Nyholm\Psr7\Response(200, [], 'Hello PSR-7');
};
