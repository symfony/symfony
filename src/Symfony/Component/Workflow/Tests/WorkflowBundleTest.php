<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Workflow\Command\WorkflowDumpCommand;
use Symfony\Component\Workflow\DataCollector\WorkflowDataCollector;
use Symfony\Component\Workflow\Debug\TraceableWorkflow;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\Order;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\OrderState;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\WorkflowConsumer;
use Symfony\Component\Workflow\Workflow;
use Symfony\Component\Workflow\WorkflowBundle;

class WorkflowBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_workflow_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testWorkflowsAreRegistered()
    {
        $kernel = new TestWorkflowKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $workflow = $container->get('test.workflow.article');
        $this->assertInstanceOf(TraceableWorkflow::class, $workflow);
        $this->assertInstanceOf(Workflow::class, $workflow->getInner());
        $this->assertSame('article', $workflow->getName());
        $this->assertSame(['draft', 'published'], array_values($workflow->getDefinition()->getPlaces()));

        $registry = $container->get('test.workflow.registry');
        $this->assertInstanceOf(Registry::class, $registry);
        $this->assertSame([$workflow], $registry->all(new \stdClass()));
    }

    public function testAttributeWorkflowIsDiscoveredAndIntegrated()
    {
        $kernel = new TestWorkflowKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $consumer = $container->get('test.workflow.consumer');
        $this->assertInstanceOf(WorkflowConsumer::class, $consumer);
        $this->assertSame($consumer->orderStateMachine, $consumer->targetedWorkflow);
        $this->assertInstanceOf(TraceableWorkflow::class, $consumer->orderStateMachine);
        $this->assertInstanceOf(StateMachine::class, $consumer->orderStateMachine->getInner());
        $this->assertFalse($container->has(OrderState::class));
        $this->assertInstanceOf(Workflow::class, $container->get('test.workflow.configured')->getInner());

        $order = new Order();
        $this->assertTrue($consumer->orderStateMachine->can($order, 'advance'));
        $consumer->orderStateMachine->apply($order, 'advance');
        $this->assertSame(OrderState::Reviewed, $order->state);

        $registry = $container->get('test.workflow.registry');
        $this->assertSame([$consumer->orderStateMachine], $registry->all($order));

        $command = $container->get('test.workflow.command');
        $this->assertInstanceOf(WorkflowDumpCommand::class, $command);
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute(['name' => 'order']));
        $this->assertStringContainsString('digraph workflow', $tester->getDisplay());
        $this->assertSame(2, substr_count($tester->getDisplay(), 'label="advance"'));

        $collector = $container->get('test.workflow.data_collector');
        $this->assertInstanceOf(WorkflowDataCollector::class, $collector);
        $collector->lateCollect();
        $workflows = $collector->getWorkflows();
        $this->assertArrayHasKey('order', $workflows);
        $this->assertSame(2, substr_count($workflows['order']['dump'], '["advance"]'));
        $this->assertGreaterThanOrEqual(2, $collector->getCallsCount());

        $kernel->shutdown();
        $kernel = new TestWorkflowKernel('test', true, $this->varDir);
        $kernel->boot();
        $rebootedConsumer = $kernel->getContainer()->get('test.workflow.consumer');
        $this->assertTrue($rebootedConsumer->orderStateMachine->can(new Order(), 'advance'));
    }
}

class TestWorkflowKernel extends AbstractKernel
{
    use KernelTrait;

    public function __construct(string $env, bool $debug, private string $dir)
    {
        parent::__construct($env, $debug);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new WorkflowBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('workflow', [
            'article' => [
                'type' => 'workflow',
                'supports' => [\stdClass::class],
                'places' => ['draft', 'published'],
                'transitions' => [
                    'publish' => ['from' => 'draft', 'to' => 'published'],
                ],
            ],
            'configured' => [
                'type' => 'workflow',
                'supports' => [\ArrayObject::class],
                'places' => ['start', 'finish'],
                'transitions' => [
                    'complete' => ['from' => 'start', 'to' => 'finish'],
                ],
            ],
        ]);

        $services = $container->services();
        $services->load('Symfony\\Component\\Workflow\\Tests\\Fixtures\\AttributeWorkflow\\', __DIR__.'/Fixtures/AttributeWorkflow/')
            ->autowire()
            ->autoconfigure();
        $services
            ->set('event_dispatcher', EventDispatcher::class)
            ->set('debug.stopwatch', Stopwatch::class)
            ->set('debug.file_link_formatter', FileLinkFormatter::class)
            ->alias('test.workflow.article', 'workflow.article')->public()
            ->alias('test.workflow.configured', 'workflow.configured')->public()
            ->alias('test.workflow.registry', 'workflow.registry')->public()
            ->alias('test.workflow.consumer', WorkflowConsumer::class)->public()
            ->alias('test.workflow.command', 'console.command.workflow_dump')->public()
            ->alias('test.workflow.data_collector', 'data_collector.workflow')->public()
        ;
    }
}
