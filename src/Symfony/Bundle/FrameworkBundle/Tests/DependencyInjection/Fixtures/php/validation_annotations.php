<?php

$container->loadFromExtension('app', 'config', array(
    'validation' => array(
        'enabled'     => true,
        'annotations' => array(
            'namespaces' => array(
                'app' => 'Application\\Validator\\Constraints\\',
            ),
        ),
    ),
));
