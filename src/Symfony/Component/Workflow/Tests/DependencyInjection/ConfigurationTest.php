<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Workflow\Tests\Fixtures\Places;
use Symfony\Component\Workflow\WorkflowBundle;

class ConfigurationTest extends TestCase
{
    public function testWorkflowEnumArcsNormalization()
    {
        $config = $this->process([
            'workflows' => [
                'enum' => [
                    'supports' => [self::class],
                    'places' => Places::cases(),
                    'initial_marking' => Places::A,
                    'transitions' => [
                        [
                            'name' => 'one',
                            'from' => [Places::A],
                            'to' => [['place' => Places::B, 'weight' => 2]],
                        ],
                        [
                            'name' => 'two',
                            'from' => ['place' => Places::B, 'weight' => 3],
                            'to' => ['place' => Places::C],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['a'], $config['workflows']['enum']['initial_marking']);

        $transitions = $config['workflows']['enum']['transitions'];

        $this->assertSame('one', $transitions[0]['name']);
        $this->assertSame([['place' => 'a', 'weight' => 1]], $transitions[0]['from']);
        $this->assertSame([['place' => 'b', 'weight' => 2]], $transitions[0]['to']);

        $this->assertSame('two', $transitions[1]['name']);
        $this->assertSame([['place' => 'b', 'weight' => 3]], $transitions[1]['from']);
        $this->assertSame([['place' => 'c', 'weight' => 1]], $transitions[1]['to']);
    }

    public function testWorkflowPlacesAndTransitionsGivenAsMaps()
    {
        $config = $this->process([
            'workflows' => [
                'blog' => [
                    'supports' => [self::class],
                    'places' => [
                        'draft' => null,
                        'review' => ['metadata' => ['description' => 'Human review']],
                    ],
                    'transitions' => [
                        'to_review' => ['from' => 'draft', 'to' => 'review'],
                        'reopen' => ['from' => 'review', 'to' => 'draft'],
                    ],
                ],
            ],
        ]);

        $this->assertEquals([['name' => 'draft', 'metadata' => []], ['name' => 'review', 'metadata' => ['description' => 'Human review']]], $config['workflows']['blog']['places']);
        $this->assertSame(['to_review', 'reopen'], array_keys($config['workflows']['blog']['transitions']));
        $this->assertSame(['to_review', 'reopen'], array_column($config['workflows']['blog']['transitions'], 'name'));
    }

    public function testWorkflowEventsToDispatchRejectsMixedAllowListAndBlockList()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Cannot mix allow-list and block-list entries in "events_to_dispatch": every entry must start with "!" (block-list mode) or none of them must (allow-list mode).');

        $this->process([
            'workflows' => [
                'mixed' => [
                    'supports' => [self::class],
                    'places' => ['a', 'b'],
                    'initial_marking' => 'a',
                    'events_to_dispatch' => ['workflow.enter', '!workflow.announce'],
                    'transitions' => [
                        ['name' => 'go', 'from' => ['a'], 'to' => ['b']],
                    ],
                ],
            ],
        ]);
    }

    public function testWorkflowEventsToDispatchAcceptsBlockListOnlyList()
    {
        $config = $this->process([
            'workflows' => [
                'block_list' => [
                    'supports' => [self::class],
                    'places' => ['a', 'b'],
                    'initial_marking' => 'a',
                    'events_to_dispatch' => ['!workflow.announce'],
                    'transitions' => [
                        ['name' => 'go', 'from' => ['a'], 'to' => ['b']],
                    ],
                ],
            ],
        ]);

        $this->assertSame(['!workflow.announce'], $config['workflows']['block_list']['events_to_dispatch']);
    }

    public function testWorkflowEventsToDispatchRejectsBlockListedGuardEvent()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "workflow.guard" event cannot be disabled in "events_to_dispatch": it is always dispatched.');

        $this->process([
            'workflows' => [
                'guard_block' => [
                    'supports' => [self::class],
                    'places' => ['a', 'b'],
                    'initial_marking' => 'a',
                    'events_to_dispatch' => ['!workflow.guard'],
                    'transitions' => [
                        ['name' => 'go', 'from' => ['a'], 'to' => ['b']],
                    ],
                ],
            ],
        ]);
    }

    #[DataProvider('provideWorkflowsGivenUnderTheRoot')]
    public function testWorkflowsGivenUnderTheRoot(array $config, array $expectedNames)
    {
        $this->assertSame($expectedNames, array_keys($this->process($config)['workflows']));
    }

    public static function provideWorkflowsGivenUnderTheRoot(): iterable
    {
        $workflow = [
            'supports' => [self::class],
            'places' => ['a', 'b'],
            'transitions' => [['name' => 'go', 'from' => ['a'], 'to' => ['b']]],
        ];

        yield 'directly' => [['enabled' => true, 'blog' => $workflow], ['blog']];
        yield 'under "workflows"' => [['enabled' => true, 'workflows' => ['blog' => $workflow]], ['blog']];
        yield 'named "workflows"' => [['enabled' => true, 'workflows' => $workflow], ['workflows']];
        yield 'as a list' => [[['name' => 'blog'] + $workflow], ['blog']];
        yield 'as a list that only enables them' => [[['enabled' => true]], []];
    }

    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(new WorkflowBundle(), null, 'workflow'), [$config]);
    }
}
