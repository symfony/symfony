<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require __DIR__.'/autoload.php';

return function (array $context): void {
    echo 'Hello World ', $context['SOME_VAR'];
};
