<?php

$container->loadFromExtension('framework', array(
    'workflows' => array(
        'enabled' => true,
        'foo' => array(
            'type' => 'workflow',
            'supports' => array('Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest'),
            'initial_place' => 'bar',
            'places' => array('bar', 'baz'),
            'transitions' => array(
                'bar_baz' => array(
                    'from' => array('foo'),
                    'to' => array('bar'),
                ),
            ),
        ),
    ),
));
