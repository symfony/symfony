<?php

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
    'exceptions' => [
        BadRequestHttpException::class => [
            'log_level' => 'info',
            'status_code' => 422,
        ],
        NotFoundHttpException::class => [
            'log_level' => 'info',
            'status_code' => null,
        ],
        ConflictHttpException::class => [
            'log_level' => 'info',
            'status_code' => 0,
        ],
        ServiceUnavailableHttpException::class => [
            'log_level' => null,
            'status_code' => 500,
        ],
    ],
]);
