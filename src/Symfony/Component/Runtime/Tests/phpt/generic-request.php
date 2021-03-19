<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Runtime\GenericRuntime;
use Symfony\Runtime\Symfony\Component\HttpFoundation\RequestRuntime;
use Symfony\Runtime\Symfony\Component\HttpFoundation\ResponseRuntime;

$_SERVER['APP_RUNTIME'] = GenericRuntime::class;
require __DIR__.'/autoload.php';

return function (Request $request, array $context) {
    echo class_exists(RequestRuntime::class, false) ? 'OK request runtime' : 'KO request runtime', "\n";

    return new StreamedResponse(function () use ($context) {
        echo 'OK Request '.$context['SOME_VAR'], "\n";
        echo class_exists(ResponseRuntime::class, false) ? 'KO response runtime' : 'OK response runtime', "\n";
    });
};
