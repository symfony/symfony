<?php

$container->loadFromExtension('app', 'config', array(
    'router' => array(
        'resource' => '%kernel.root_dir%/config/routing.xml',
    ),
    'validation' => array(
        'enabled'     => true,
        'annotations' => true,
        'namespaces' => array(
            'app' => 'Application\\Validator\\Constraints\\',
        ),
    ),
    'templating' => array(
        'engine' => 'php'
    ),
));
