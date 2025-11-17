<?php

$container->loadFromExtension('framework', [
    'csrf_protection' => [
        'enabled' => true,
    ],
    'form' => [
        'csrf_protection' => [
            'field-attr' => [
                'data-foo' => 'bar',
                'data-bar' => 'baz',
            ],
        ],
    ],
    'session' => [
        'storage_factory_id' => 'session.storage.factory.native',
    ],
]);
