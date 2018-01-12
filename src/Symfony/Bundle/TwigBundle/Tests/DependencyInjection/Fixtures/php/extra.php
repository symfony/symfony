<?php

$container->loadFromExtension('twig', array(
    'paths' => array(
        'namespaced_path3' => 'namespace3',
    ),
    'strict_variables' => false, // to be removed in 5.0 relying on default
));
