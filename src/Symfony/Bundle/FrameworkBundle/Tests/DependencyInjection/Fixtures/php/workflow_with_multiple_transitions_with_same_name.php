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
            'initial_marking' => ['draft'],
            'places' => [
                'draft',
                'wait_for_journalist',
                'approved_by_journalist',
                'wait_for_spellchecker',
                'approved_by_spellchecker',
                'published',
            ],
            // We also test different configuration formats here
            'transitions' => [
                'request_review' => [
                    'from' => 'draft',
                    'to' => ['wait_for_journalist', 'wait_for_spellchecker'],
                ],
                'journalist_approval' => [
                    'from' => ['wait_for_journalist'],
                    'to' => 'approved_by_journalist',
                ],
                'spellchecker_approval' => [
                    'from' => 'wait_for_spellchecker',
                    'to' => 'approved_by_spellchecker',
                ],
                'publish' => [
                    'from' => [['place' => 'approved_by_journalist', 'weight' => 1], 'approved_by_spellchecker'],
                    'to' => 'published',
                ],
                'publish_editor_in_chief' => [
                    'name' => 'publish',
                    'from' => 'draft',
                    'to' => [['place' => 'published', 'weight' => 2]],
                ],
            ],
        ],
    ],
]);
