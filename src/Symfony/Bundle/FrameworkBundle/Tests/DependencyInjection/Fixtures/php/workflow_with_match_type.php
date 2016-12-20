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
            'places' => array(
                'first',
                'second',
                'last',
            ),
            'transitions' => array(
                'go' => array(
                    'match' => 'one',
                    'from' => array(
                        'first',
                        'second',
                    ),
                    'to' => array(
                        'last',
                    ),
                ),
            ),
        ),
    ),
));
