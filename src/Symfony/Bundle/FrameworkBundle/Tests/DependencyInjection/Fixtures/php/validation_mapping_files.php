<?php

$container->loadFromExtension('framework', array(
    'validation' => array(
        'mapping' => array(
            'files' => array('%kernel.root_dir%/Fixtures/TestBundle/Resources/config/validation.yml'),
        ),
    ),
));
