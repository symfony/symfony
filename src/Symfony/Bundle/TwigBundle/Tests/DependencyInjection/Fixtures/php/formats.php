<?php

$container->loadFromExtension('twig', [
    'date' => [
        'format' => 'Y-m-d',
        'interval_format' => '%d',
        'timezone' => 'Europe/Berlin',
    ],
    'number_format' => [
        'decimals' => 2,
        'decimal_point' => ',',
        'thousands_separator' => '.',
    ],
]);
