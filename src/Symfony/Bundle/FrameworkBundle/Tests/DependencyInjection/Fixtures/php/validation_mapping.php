<?php

$container->loadFromExtension('framework', array(
    'validation' => array(
        'mapping' => array(
            'paths' => array(
                '%kernel.root_dir%/Fixtures/TestBundle/Resources/config/validation_mapping/files',
                '%kernel.root_dir%/Fixtures/TestBundle/Resources/config/validation_mapping/validation.yml',
                '%kernel.root_dir%/Fixtures/TestBundle/Resources/config/validation_mapping/validation.yaml',
            ),
        ),
    ),
));
