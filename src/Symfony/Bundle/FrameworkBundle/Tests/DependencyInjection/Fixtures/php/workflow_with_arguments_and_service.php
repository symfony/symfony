<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', array(
    'workflows' => array(
        'my_workflow' => array(
            'marking_store' => array(
                'arguments' => array('a', 'b'),
                'service' => 'workflow_service',
            ),
            'supports' => array(
                FrameworkExtensionTest::class,
            ),
            'places' => array(
                'first',
                'last',
            ),
            'transitions' => array(
                'go' => array(
                    'from' => array(
                        'first',
                    ),
                    'to' => array(
                        'last',
                    ),
                ),
            ),
        ),
    ),
));
