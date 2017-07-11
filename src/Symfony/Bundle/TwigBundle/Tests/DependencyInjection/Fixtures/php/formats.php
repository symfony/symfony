<?php

$container->loadFromExtension('twig', array(
    'date' => array(
        'format' => 'Y-m-d',
        'interval_format' => '%d',
        'timezone' => 'Europe/Berlin',
    ),
    'number_format' => array(
        'decimals' => 2,
        'decimal_point' => ',',
        'thousands_separator' => '.',
    ),
));
