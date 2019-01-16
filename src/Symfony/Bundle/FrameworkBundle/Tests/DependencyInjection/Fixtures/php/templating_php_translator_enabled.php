<?php

$container->loadFromExtension('framework', [
    'translator' => true,
    'templating' => [
        'engines' => ['php'],
    ],
]);
