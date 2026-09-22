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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\Task;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskConsumer;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskStep;
use Symfony\Component\Workflow\Tests\Fixtures\AttributeWorkflow\TaskWorkflow;
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
        $this->assertInstanceOf(Workflow::class, $workflow);
        $this->assertSame('article', $workflow->getName());
        $this->assertSame(['draft', 'published'], array_values($workflow->getDefinition()->getPlaces()));

        $registry = $container->get('test.workflow.registry');
        $this->assertInstanceOf(Registry::class, $registry);
        $this->assertSame([$workflow], $registry->all(new \stdClass()));
    }

    public function testWorkflowsAreDefinedWithAttributes()
    {
        $kernel = new TestWorkflowKernel('test', true, $this->varDir);
        $kernel->boot();
        $container = $kernel->getContainer();

        $consumer = $container->get('test.workflow.task_consumer');
        $this->assertInstanceOf(TaskConsumer::class, $consumer);
        $this->assertInstanceOf(TaskWorkflow::class, $consumer->taskWorkflow);
        $this->assertInstanceOf(StateMachine::class, $consumer->workflow);
        $this->assertSame('task', $consumer->taskWorkflow->getName());
        $this->assertSame($consumer->workflow->getDefinition(), $consumer->taskWorkflow->getDefinition());
        $this->assertSame(['new', 'processing', 'done', 'failed'], array_values($consumer->taskWorkflow->getDefinition()->getPlaces()));
        $this->assertSame(['new'], $consumer->taskWorkflow->getDefinition()->getInitialPlaces());
        $this->assertSame('Task', $consumer->taskWorkflow->getMetadataStore()->getMetadata('title'));
        $this->assertSame('Processing', $consumer->taskWorkflow->getMetadataStore()->getMetadata('label', TaskStep::Processing->value));

        $task = new Task();
        $this->assertTrue($consumer->taskWorkflow->can($task, TaskWorkflow::START));
        $consumer->taskWorkflow->start($task);
        $this->assertSame(TaskStep::Processing, $task->step);
        $this->assertSame(['started_by' => 'start()'], $task->context, 'The transition listener of the class is registered');
        $this->assertTrue($consumer->taskWorkflow->can($task, TaskWorkflow::FINISH));
        $this->assertTrue($consumer->taskWorkflow->can($task, TaskWorkflow::FAIL));

        $blockedTask = new Task();
        $blockedTask->blocked = true;
        $this->assertFalse($consumer->taskWorkflow->can($blockedTask, TaskWorkflow::START), 'The guard listener of the class is registered');

        $registry = $container->get('test.workflow.registry');
        $this->assertSame([$consumer->workflow], $registry->all($task));

        $command = new CommandTester($container->get('test.workflow.dump_command'));
        $this->assertSame(0, $command->execute(['name' => 'task']));
        $this->assertStringContainsString('digraph workflow', $command->getDisplay());
        $this->assertSame(2, substr_count($command->getDisplay(), 'label="finish"'));
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
        ]);

        $services = $container->services()->defaults()->autowire()->autoconfigure();
        $services->load('Symfony\\Component\\Workflow\\Tests\\Fixtures\\AttributeWorkflow\\', __DIR__.'/Fixtures/AttributeWorkflow/');
        $services->alias('test.workflow.article', 'workflow.article')->public();
        $services->alias('test.workflow.registry', 'workflow.registry')->public();
        $services->alias('test.workflow.task_consumer', TaskConsumer::class)->public();
        $services->alias('test.workflow.dump_command', 'console.command.workflow_dump')->public();
    }
}
