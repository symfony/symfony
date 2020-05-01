<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require __DIR__.'/autoload.php';

return function (Request $request, array $context) {
    return new Response('OK Request '.$context['SOME_VAR']);
};
