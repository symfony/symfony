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
            ),
        ),
        'pull_request' => array(
            'marking_store' => array(
                'type' => 'single_state',
            ),
            'supports' => array(
                FrameworkExtensionTest::class,
            ),
            'initial_place' => 'start',
            'metadata' => array(
                'title' => 'workflow title',
            ),
            'places' => array(
                'start_name_not_used' => array(
                    'name' => 'start',
                    'metadata' => array(
                        'title' => 'place start title',
                    ),
                ),
                'coding' => null,
                'travis' => null,
                'review' => null,
                'merged' => null,
                'closed' => null,
            ),
            'transitions' => array(
                'submit' => array(
                    'from' => 'start',
                    'to' => 'travis',
                    'metadata' => array(
                        'title' => 'transition submit title',
                    ),
                ),
                'update' => array(
                    'from' => array('coding', 'travis', 'review'),
                    'to' => 'travis',
                ),
                'wait_for_review' => array(
                    'from' => 'travis',
                    'to' => 'review',
                ),
                'request_change' => array(
                    'from' => 'review',
                    'to' => 'coding',
                ),
                'accept' => array(
                    'from' => 'review',
                    'to' => 'merged',
                ),
                'reject' => array(
                    'from' => 'review',
                    'to' => 'closed',
                ),
                'reopen' => array(
                    'from' => 'closed',
                    'to' => 'review',
                ),
            ),
        ),
        'service_marking_store_workflow' => array(
            'type' => 'workflow',
            'marking_store' => array(
                'service' => 'workflow_service',
            ),
            'supports' => array(
                FrameworkExtensionTest::class,
            ),
            'places' => array(
                array('name' => 'first'),
                array('name' => 'last'),
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
