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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Workflow\DependencyInjection\Configuration\ArcConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\PlaceConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\TransitionConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\WorkflowConfig;
use Symfony\Component\Workflow\DependencyInjection\WorkflowServiceRegistrar;
use Symfony\Component\Workflow\WorkflowType;

final class WorkflowServiceRegistrarTest extends TestCase
{
    #[DataProvider('provideWorkflowTypes')]
    public function testGeneratedServiceIdsMatchRegisteredDefinitionsAndAliases(WorkflowType $type)
    {
        $workflow = new WorkflowConfig(
            'parity.check',
            $type,
            [new PlaceConfig('a'), new PlaceConfig('b'), new PlaceConfig('c'), new PlaceConfig('d')],
            [new TransitionConfig('advance', [new ArcConfig('a'), new ArcConfig('b')], [new ArcConfig('c'), new ArcConfig('d')], 'true')],
            ['a'],
            [\stdClass::class],
            null,
            [],
            null,
            null,
            true,
            null,
            [],
        );
        $container = new ContainerBuilder();
        $container->register('workflow.registry');
        $definitionIds = array_keys($container->getDefinitions());
        $aliasIds = array_keys($container->getAliases());
        $registrar = new WorkflowServiceRegistrar();

        $expectedIds = $registrar->getGeneratedServiceIds($workflow);
        $registrar->register($container, $workflow);
        $actualIds = [
            ...array_diff(array_keys($container->getDefinitions()), $definitionIds),
            ...array_diff(array_keys($container->getAliases()), $aliasIds),
        ];
        sort($expectedIds);
        sort($actualIds);

        $this->assertSame($expectedIds, $actualIds);
    }

    public static function provideWorkflowTypes(): iterable
    {
        yield 'workflow' => [WorkflowType::Workflow];
        yield 'state machine' => [WorkflowType::StateMachine];
    }
}
