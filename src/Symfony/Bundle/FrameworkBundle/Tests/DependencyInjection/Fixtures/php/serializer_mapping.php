<?php

$container->loadFromExtension('framework', array(
    'annotations' => array('enabled' => true),
    'serializer' => array(
        'enable_annotations' => true,
        'mapping' => array(
            'paths' => array(
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/files',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yml',
                '%kernel.project_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yaml',
            ),
        ),
    ),
));
