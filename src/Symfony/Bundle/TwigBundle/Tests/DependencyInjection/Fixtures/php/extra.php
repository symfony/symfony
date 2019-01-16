<?php

$container->loadFromExtension('twig', [
    'paths' => [
        'namespaced_path3' => 'namespace3',
    ],
    'strict_variables' => false, // to be removed in 5.0 relying on default
]);
