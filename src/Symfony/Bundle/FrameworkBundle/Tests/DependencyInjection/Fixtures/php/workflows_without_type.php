<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', array(
    'workflows' => array(
        'missing_type' => array(
            'marking_store' => array(
                'service' => 'workflow_service',
            ),
            'supports' => array(
                \stdClass::class,
            ),
            'places' => array(
                'first',
                'last',
            ),
            'transitions' => array(
                'go' => array(
                    'from' => 'first',
                    'to' => 'last',
                ),
            ),
        ),
    ),
));
