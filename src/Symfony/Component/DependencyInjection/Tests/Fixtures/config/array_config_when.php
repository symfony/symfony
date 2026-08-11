<?php

return [
    'services' => [
        'my_conditional_service' => [
            'class' => \stdClass::class,
            'when' => [
                'class_exists' => 'Redis',
                'missing_service' => 'bar',
                'parameter' => ['name' => 'app.enabled', 'value' => true],
            ],
        ],
        'Symfony\Component\DependencyInjection\Tests\Fixtures\Prototype\Sub\\' => [
            'resource' => '../Prototype/Sub/*',
            'when' => [
                'missing_service' => 'app.manager',
            ],
        ],
    ],
];
