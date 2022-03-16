<?php

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

$container->loadFromExtension('framework', [
    'exceptions' => [
        BadRequestHttpException::class => [
            'log_level' => 'info',
            'status_code' => 422,
        ],
    ],
]);
