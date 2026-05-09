<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ConsoleBundleTest extends TestCase
{
    private string $varDir;

    protected function setUp(): void
    {
        $this->varDir = sys_get_temp_dir().'/sf_console_bundle_test';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->varDir);
    }

    public function testCommandsAreDiscovered()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $app = new Application('test', '1.0', $kernel->getContainer());
        $app->setAutoExit(false);

        $this->assertTrue($app->has('test:hello'));
    }

    public function testEventDispatcherIsRegistered()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has('event_dispatcher'));
        $this->assertInstanceOf(EventDispatcherInterface::class, $kernel->getContainer()->get('event_dispatcher'));
    }

    public function testArgumentResolverIsRegistered()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has('console.argument_resolver'));
    }

    public function testCommandLoaderIsRegistered()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has('console.command_loader'));
    }

    public function testApplicationWiresServicesFromContainer()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $app = new Application('test', '1.0', $kernel->getContainer());
        $app->setAutoExit(false);

        $app->all();

        $this->assertNotNull($app->getDispatcher());
        $this->assertNotNull($app->getArgumentResolver());
    }

    public function testCommandCanBeRun()
    {
        $kernel = $this->createKernel();
        $kernel->boot();

        $app = new Application('test', '1.0', $kernel->getContainer());
        $app->setAutoExit(false);

        $tester = new ApplicationTester($app);
        $tester->run(['command' => 'test:hello']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Hello!', $tester->getDisplay());
    }

    private function createKernel(): TestConsoleKernel
    {
        return new TestConsoleKernel('test', true, $this->varDir);
    }
}

class TestConsoleKernel extends AbstractKernel
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
        yield new ConsoleBundle();
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->register(ConsoleBundleTestCommand::class, ConsoleBundleTestCommand::class)
            ->addTag('console.command');
    }
}

#[AsCommand(name: 'test:hello', description: 'A test command')]
class ConsoleBundleTestCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->write('Hello!');

        return Command::SUCCESS;
    }
}
