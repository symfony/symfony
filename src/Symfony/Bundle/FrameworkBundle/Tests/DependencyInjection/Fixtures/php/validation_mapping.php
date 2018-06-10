<?php

$container->loadFromExtension('framework', array(
    'validation' => array(
        'mapping' => array(
            'paths' => array(
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/validation_mapping/files',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/validation_mapping/validation.yml',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/validation_mapping/validation.yaml',
            ),
        ),
    ),
));
