<?php

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'exceptions' => [
        BadRequestHttpException::class => [
            'log_level' => 'info',
            'status_code' => 422,
        ],
    ],
]);
