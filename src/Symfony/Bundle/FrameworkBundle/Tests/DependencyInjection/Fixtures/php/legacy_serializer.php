<?php

$container->loadFromExtension('framework', [
    'serializer' => [
        'enabled' => true,
        'enable_attributes' => true,
        'name_converter' => 'serializer.name_converter.camel_case_to_snake_case',
        'circular_reference_handler' => 'my.circular.reference.handler',
        'max_depth_handler' => 'my.max.depth.handler',
        'default_context' => ['enable_max_depth' => true],
    ],
]);
