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
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Workflow\Registry;
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
        $container->services()->alias('test.workflow.article', 'workflow.article')->public();
        $container->services()->alias('test.workflow.registry', 'workflow.registry')->public();
    }
}
