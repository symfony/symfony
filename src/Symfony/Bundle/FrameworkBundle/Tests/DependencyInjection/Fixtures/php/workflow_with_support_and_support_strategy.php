<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', array(
    'workflows' => array(
        'my_workflow' => array(
            'marking_store' => array(
                'type' => 'multiple_state',
            ),
            'supports' => array(
                FrameworkExtensionTest::class,
            ),
            'support_strategy' => 'foobar',
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
