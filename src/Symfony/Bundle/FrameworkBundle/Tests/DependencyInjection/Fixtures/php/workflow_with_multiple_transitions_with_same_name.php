<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTest;

$container->loadFromExtension('framework', array(
    'workflows' => array(
        'article' => array(
            'type' => 'workflow',
            'marking_store' => array(
                'type' => 'multiple_state',
            ),
            'supports' => array(
                FrameworkExtensionTest::class,
            ),
            'initial_place' => 'draft',
            'places' => array(
                'draft',
                'wait_for_journalist',
                'approved_by_journalist',
                'wait_for_spellchecker',
                'approved_by_spellchecker',
                'published',
            ),
            'transitions' => array(
                'request_review' => array(
                    'from' => 'draft',
                    'to' => array('wait_for_journalist', 'wait_for_spellchecker'),
                ),
                'journalist_approval' => array(
                    'from' => 'wait_for_journalist',
                    'to' => 'approved_by_journalist',
                ),
                'spellchecker_approval' => array(
                    'from' => 'wait_for_spellchecker',
                    'to' => 'approved_by_spellchecker',
                ),
                'publish' => array(
                    'from' => array('approved_by_journalist', 'approved_by_spellchecker'),
                    'to' => 'published',
                ),
                'publish_editor_in_chief' => array(
                    'name' => 'publish',
                    'from' => 'draft',
                    'to' => 'published',
                ),
            ),
        ),
    ),
));
