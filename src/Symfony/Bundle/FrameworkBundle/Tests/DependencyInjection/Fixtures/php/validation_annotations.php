<?php

$container->loadFromExtension('framework', array(
    'validation' => array(
        'enabled'     => true,
        'annotations' => array(
            'namespaces' => array(
                'app' => 'Application\\Validator\\Constraints\\',
            ),
        ),
    ),
));
