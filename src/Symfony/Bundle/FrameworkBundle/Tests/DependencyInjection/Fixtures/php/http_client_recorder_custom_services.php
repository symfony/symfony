<?php

use Symfony\Component\HttpClient\Recorder\Matcher\DefaultMatcher;
use Symfony\Component\HttpClient\Recorder\Redactor\DefaultRedactor;

$container->register('my_matcher', DefaultMatcher::class);
$container->register('my_redactor', DefaultRedactor::class);

$container->loadFromExtension('framework', [
    'http_client' => [
        'recorder' => [
            'enabled' => true,
            'matcher' => 'my_matcher',
            'redactor' => 'my_redactor',
        ],
    ],
]);
