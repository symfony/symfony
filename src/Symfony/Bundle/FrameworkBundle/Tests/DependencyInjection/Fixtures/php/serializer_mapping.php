<?php

$container->loadFromExtension('framework', array(
    'annotations' => array('enabled' => true),
    'serializer' => array(
        'enable_annotations' => true,
        'mapping' => array(
            'paths' => array(
                '%kernel.root_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/files',
                '%kernel.root_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yml',
                '%kernel.root_dir%/Fixtures/TestBundle/Resources/config/serializer_mapping/serialization.yaml',
            ),
        ),
    ),
));
