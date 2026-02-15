<?php

$container->loadFromExtension('framework', [
    'translator' => [
        'globals' => [
            '%%app_name%%' => 'My application',
            '{app_version}' => '1.2.3',
            '{url}' => ['message' => 'url', 'parameters' => ['scheme' => 'https://'], 'domain' => 'global'],
        ],
    ],
]);
