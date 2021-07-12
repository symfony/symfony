<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$_SERVER['SOME_VAR'] = 'ccc';
$_SERVER['APP_RUNTIME_OPTIONS'] = [
    'dotenv_overload' => true,
];

require __DIR__.'/autoload.php';

return function (Request $request, array $context) {
    return new Response('OK Request '.$context['SOME_VAR']);
};
