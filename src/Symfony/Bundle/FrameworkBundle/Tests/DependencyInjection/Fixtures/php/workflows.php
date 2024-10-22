<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'annotations' => false,
    'http_method_override' => false,
    'handle_all_throwables' => true,
    'php_errors' => ['log' => true],
    'workflows' => [
        'article' => [
            'type' => 'workflow',
            'supports' => [
                FrameworkExtensionTestCase::class,
            ],
            'definition_validators' => [
                Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Fixtures\Workflow\Validator\DefinitionValidator::class,
            ],
            'initial_marking' => ['draft'],
            'metadata' => [
                'title' => 'article workflow',
                'description' => 'workflow for articles',
            ],
            'places' => [
                'draft',
                'wait_for_journalist',
                'approved_by_journalist',
                'wait_for_spellchecker',
                'approved_by_spellchecker',
                'published',
            ],
            'transitions' => [
                'request_review' => [
                    'from' => 'draft',
                    'to' => ['wait_for_journalist', 'wait_for_spellchecker'],
                ],
                'journalist_approval' => [
                    'from' => 'wait_for_journalist',
                    'to' => 'approved_by_journalist',
                ],
                'spellchecker_approval' => [
                    'from' => 'wait_for_spellchecker',
                    'to' => 'approved_by_spellchecker',
                ],
                'publish' => [
                    'from' => ['approved_by_journalist', 'approved_by_spellchecker'],
                    'to' => 'published',
                ],
            ],
        ],
        'pull_request' => [
            'supports' => [
                FrameworkExtensionTestCase::class,
            ],
            'initial_marking' => 'start',
            'metadata' => [
                'title' => 'workflow title',
            ],
            'places' => [
                'start_name_not_used' => [
                    'name' => 'start',
                    'metadata' => [
                        'title' => 'place start title',
                    ],
                ],
                'coding' => null,
                'travis' => null,
                'review' => null,
                'merged' => null,
                'closed' => null,
            ],
            'transitions' => [
                'submit' => [
                    'from' => 'start',
                    'to' => 'travis',
                    'metadata' => [
                        'title' => 'transition submit title',
                    ],
                ],
                'update' => [
                    'from' => ['coding', 'travis', 'review'],
                    'to' => 'travis',
                ],
                'wait_for_review' => [
                    'from' => 'travis',
                    'to' => 'review',
                ],
                'request_change' => [
                    'from' => 'review',
                    'to' => 'coding',
                ],
                'accept' => [
                    'from' => 'review',
                    'to' => 'merged',
                ],
                'reject' => [
                    'from' => 'review',
                    'to' => 'closed',
                ],
                'reopen' => [
                    'from' => 'closed',
                    'to' => 'review',
                ],
            ],
        ],
        'service_marking_store_workflow' => [
            'type' => 'workflow',
            'marking_store' => [
                'service' => 'workflow_service',
            ],
            'supports' => [
                FrameworkExtensionTestCase::class,
            ],
            'places' => [
                ['name' => 'first'],
                ['name' => 'last'],
            ],
            'transitions' => [
                'go' => [
                    'from' => 'first',
                    'to' => 'last',
                ],
            ],
        ],
    ],
]);
