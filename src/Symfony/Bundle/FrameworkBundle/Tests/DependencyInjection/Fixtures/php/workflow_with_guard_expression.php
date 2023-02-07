<?php

use Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\FrameworkExtensionTestCase;

$container->loadFromExtension('framework', [
    'http_method_override' => false,
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
                    'guard' => '!!true',
                ],
                'publish_editor_in_chief' => [
                    'name' => 'publish',
                    'from' => 'draft',
                    'to' => 'published',
                    'guard' => '!!false',
                ],
            ],
        ],
    ],
]);
