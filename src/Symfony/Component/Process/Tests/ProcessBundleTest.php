<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Messenger\RunProcessMessage;
use Symfony\Component\Process\Messenger\RunProcessMessageHandler;
use Symfony\Component\Process\ProcessBundle;

class ProcessBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_process_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testProcessMessageHandlerIsRegistered()
    {
        $kernel = new TestProcessKernel('test', true, $this->varDir);
        $kernel->boot();

        $this->assertInstanceOf(RunProcessMessageHandler::class, $kernel->getContainer()->get('test.process.messenger.process_message_handler'));
    }

    public function testProcessMessageHandlerRunsTheCommand()
    {
        $kernel = new TestProcessKernel('test', true, $this->varDir);
        $kernel->boot();

        $handler = $kernel->getContainer()->get('test.process.messenger.process_message_handler');
        $context = $handler(new RunProcessMessage(['ls'], __DIR__));

        $this->assertSame(0, $context->exitCode);
        $this->assertStringContainsString(basename(__FILE__), $context->output);
    }
}

class TestProcessKernel extends AbstractKernel
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
        yield new ProcessBundle();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()->alias('test.process.messenger.process_message_handler', 'process.messenger.process_message_handler')->public();
    }
}
