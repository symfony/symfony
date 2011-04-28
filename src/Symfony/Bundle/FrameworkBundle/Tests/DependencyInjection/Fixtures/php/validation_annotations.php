<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'validation' => array(
        'enabled'     => true,
        'annotations' => array(
            'namespaces' => array(
                'app' => 'Application\\Validator\\Constraints\\',
            ),
        ),
    ),
));
