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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\WorkflowBundle;
use Symfony\Component\Workflow\WorkflowType;

class WorkflowServiceRegistrarTest extends TestCase
{
    private const WORKFLOW_CLASSES = [
        'state_machine' => [
            'foo' => CollisionFooStateMachine::class,
            'foo.definition' => CollisionDefinitionStateMachine::class,
            'foo.metadata_store' => CollisionMetadataStoreStateMachine::class,
        ],
        'workflow' => [
            'foo' => CollisionFooWorkflow::class,
            'foo.definition' => CollisionDefinitionWorkflow::class,
            'foo.metadata_store' => CollisionMetadataStoreWorkflow::class,
        ],
    ];

    #[DataProvider('provideCollidingWorkflowNames')]
    public function testConfiguredWorkflowsCannotOverwriteEachOther(string $type, string $first, string $second, string $serviceId)
    {
        $container = $this->createContainer([$first => $this->createWorkflow($type), $second => $this->createWorkflow($type)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot register workflow "%s": service ID "%s" already exists.', $second, $serviceId));

        $container->compile();
    }

    #[DataProvider('provideCollidingWorkflowNames')]
    public function testAttributedWorkflowsCannotOverwriteEachOther(string $type, string $first, string $second, string $serviceId)
    {
        $container = $this->createContainer([], [self::WORKFLOW_CLASSES[$type][$first], self::WORKFLOW_CLASSES[$type][$second]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot register workflow "%s": service ID "%s" already exists.', $second, $serviceId));

        $container->compile();
    }

    #[DataProvider('provideCollidingWorkflowNames')]
    public function testAttributedWorkflowsCannotOverwriteConfiguredWorkflows(string $type, string $first, string $second, string $serviceId)
    {
        $container = $this->createContainer([$first => $this->createWorkflow($type)], [self::WORKFLOW_CLASSES[$type][$second]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot register workflow "%s": service ID "%s" already exists.', $second, $serviceId));

        $container->compile();
    }

    public static function provideCollidingWorkflowNames(): iterable
    {
        foreach (['state_machine', 'workflow'] as $type) {
            foreach (['definition', 'metadata_store'] as $suffix) {
                yield $type.' '.$suffix => [$type, 'foo', 'foo.'.$suffix, $type.'.foo.'.$suffix];
                yield $type.' '.$suffix.' reversed' => [$type, 'foo.'.$suffix, 'foo', $type.'.foo.'.$suffix];
            }
        }
    }

    #[DataProvider('provideBundleServiceIds')]
    public function testWorkflowsCannotOverwriteServicesOfTheBundle(string $type, string $name, string $serviceId)
    {
        $container = $this->createContainer([$name => $this->createWorkflow($type)]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(\sprintf('Cannot register workflow "%s": service ID "%s" already exists.', $name, $serviceId));

        $container->compile();
    }

    public static function provideBundleServiceIds(): iterable
    {
        yield ['workflow', 'registry', 'workflow.registry'];
        yield ['workflow', 'abstract', 'workflow.abstract'];
        yield ['state_machine', 'abstract', 'state_machine.abstract'];
    }

    #[DataProvider('provideWorkflowTypes')]
    public function testWorkflowsSharingAnAutowiringAliasAreRegistered(string $type)
    {
        $container = $this->createContainer(['foo.bar' => $this->createWorkflow($type), 'foo_bar' => $this->createWorkflow($type)]);
        $container->setAlias('test.foo.bar', $type.'.foo.bar')->setPublic(true);
        $container->setAlias('test.foo_bar', $type.'.foo_bar')->setPublic(true);

        $container->compile();

        $this->assertSame('foo.bar', $container->findDefinition('test.foo.bar')->getArgument(3));
        $this->assertSame('foo_bar', $container->findDefinition('test.foo_bar')->getArgument(3));
    }

    public static function provideWorkflowTypes(): iterable
    {
        yield ['state_machine'];
        yield ['workflow'];
    }

    public function testTheAppCanOverrideAServiceOfAConfiguredWorkflow()
    {
        $container = $this->createContainer(['foo' => $this->createWorkflow('state_machine')]);
        $container->register('state_machine.foo.metadata_store', InMemoryMetadataStore::class)->setArguments([['title' => 'app']])->setPublic(true);

        $container->compile();

        $this->assertSame([['title' => 'app']], $container->getDefinition('state_machine.foo.metadata_store')->getArguments());
    }

    private function createContainer(array $workflows = [], array $classes = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', false);
        $bundle = new WorkflowBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $bundle->build($container);
        $container->loadFromExtension('workflow', $workflows);
        foreach ($classes as $class) {
            $container->register($class, $class)->setAutoconfigured(true);
        }

        return $container;
    }

    private function createWorkflow(string $type): array
    {
        return [
            'type' => $type,
            'places' => ['a', 'b'],
            'transitions' => ['go' => ['from' => 'a', 'to' => 'b']],
        ];
    }
}

trait CollisionTransitions
{
    #[Transition(from: 'a', to: 'b')]
    public const GO = 'go';
}

#[AsWorkflow(name: 'foo')]
class CollisionFooStateMachine
{
    use CollisionTransitions;
}

#[AsWorkflow(name: 'foo.definition')]
class CollisionDefinitionStateMachine
{
    use CollisionTransitions;
}

#[AsWorkflow(name: 'foo.metadata_store')]
class CollisionMetadataStoreStateMachine
{
    use CollisionTransitions;
}

#[AsWorkflow(name: 'foo', type: WorkflowType::Workflow)]
class CollisionFooWorkflow
{
    use CollisionTransitions;
}

#[AsWorkflow(name: 'foo.definition', type: WorkflowType::Workflow)]
class CollisionDefinitionWorkflow
{
    use CollisionTransitions;
}

#[AsWorkflow(name: 'foo.metadata_store', type: WorkflowType::Workflow)]
class CollisionMetadataStoreWorkflow
{
    use CollisionTransitions;
}
