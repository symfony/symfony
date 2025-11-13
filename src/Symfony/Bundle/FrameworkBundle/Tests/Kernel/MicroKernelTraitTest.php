<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Kernel;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

require_once __DIR__.'/default/src/DefaultKernel.php';
require_once __DIR__.'/flex-style/src/FlexStyleMicroKernel.php';

class MicroKernelTraitTest extends TestCase
{
    private ?Kernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel) {
            $kernel = $this->kernel;
            $this->kernel = null;
            $fs = new Filesystem();
            $fs->remove($kernel->getCacheDir());
            $fs->remove($kernel->getProjectDir().'/config/reference.php');
        }
    }

    #[BackupGlobals(true)]
    public function testGetShareDirDisabledByEnv()
    {
        $_SERVER['APP_SHARE_DIR'] = 'false';

        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);

        $this->assertNull($kernel->getShareDir());

        $parameters = $kernel->getKernelParameters();
        $this->assertArrayNotHasKey('kernel.share_dir', $parameters);
    }

    #[BackupGlobals(true)]
    public function testGetShareDirCustomPathFromEnv()
    {
        $_SERVER['APP_SHARE_DIR'] = sys_get_temp_dir();

        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);

        $expected = rtrim(sys_get_temp_dir(), '/').'/test';
        $this->assertSame($expected, $kernel->getShareDir());

        $parameters = $kernel->getKernelParameters();
        $this->assertArrayHasKey('kernel.share_dir', $parameters);
        $this->assertNotNull($parameters['kernel.share_dir']);
        $this->assertSame(realpath($expected), realpath($parameters['kernel.share_dir']));
    }

    public function test()
    {
        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertEquals('halloween', $response->getContent());
        $this->assertEquals('Have a great day!', $kernel->getContainer()->getParameter('halloween'));
        $this->assertInstanceOf(\stdClass::class, $kernel->getContainer()->get('halloween'));
    }

    public function testAsEventSubscriber()
    {
        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/danger');
        $response = $kernel->handle($request);

        $this->assertSame('It\'s dangerous to go alone. Take this ⚔', $response->getContent());
    }

    public function testRoutingRouteLoaderTagIsAdded()
    {
        $frameworkExtension = $this->createMock(ExtensionInterface::class);
        $frameworkExtension
            ->expects($this->atLeastOnce())
            ->method('getAlias')
            ->willReturn('framework');
        $container = new ContainerBuilder();
        $container->registerExtension($frameworkExtension);
        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);
        $kernel->registerContainerConfiguration(new ClosureLoader($container));
        $this->assertTrue($container->getDefinition('kernel')->hasTag('routing.route_loader'));
    }

    public function testFlexStyle()
    {
        $kernel = $this->kernel = new FlexStyleMicroKernel('test', false);
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request);

        $this->assertEquals('Have a great day!', $response->getContent());

        $request = Request::create('/h');
        $response = $kernel->handle($request);

        $this->assertEquals('Have a great day!', $response->getContent());

        $request = Request::create('/easter');
        $response = $kernel->handle($request);

        $this->assertSame('easter', $response->getContent());
    }

    public function testSecretLoadedFromExtension()
    {
        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);
        $kernel->boot();

        self::assertSame('$ecret', $kernel->getContainer()->getParameter('kernel.secret'));
    }

    public function testAnonymousMicroKernel()
    {
        $kernel = $this->kernel = new class('anonymous_kernel') extends MinimalKernel {
            public function helloAction(): Response
            {
                return new Response('Hello World!');
            }

            protected function configureContainer(ContainerConfigurator $c): void
            {
                $c->extension('framework', [
                    'annotations' => false,
                    'http_method_override' => false,
                    'handle_all_throwables' => true,
                    'php_errors' => ['log' => true],
                    'router' => ['utf8' => true],
                ]);
                $c->services()->set('logger', NullLogger::class);
            }

            protected function configureRoutes(RoutingConfigurator $routes): void
            {
                $routes->add('hello', '/')->controller($this->helloAction(...));
            }
        };

        $request = Request::create('/');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertSame('Hello World!', $response->getContent());
    }

    public function testSimpleKernel()
    {
        $kernel = $this->kernel = new SimpleKernel('simple_kernel');
        $kernel->boot();

        $request = Request::create('/');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertSame('Hello World!', $response->getContent());
    }

    public function testKernelCommand()
    {
        if (!property_exists(AsCommand::class, 'help')) {
            $this->markTestSkipped('Invokable command no available.');
        }

        $kernel = $this->kernel = new KernelCommand('kernel_command');
        $application = new Application($kernel);

        $input = new ArrayInput(['command' => 'kernel:hello']);
        $output = new BufferedOutput();

        $this->assertTrue($application->has('kernel:hello'));
        $this->assertSame(0, $application->doRun($input, $output));
        $this->assertSame('Hello Kernel!', $output->fetch());
    }

    public function testDefaultKernel()
    {
        $kernel = $this->kernel = new DefaultKernel('test', false);
        $kernel->boot();

        $this->assertTrue($kernel->getContainer()->has('foo_service'));

        $request = Request::create('/');
        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

        $this->assertSame('OK', $response->getContent());
    }

    public function testGetKernelParameters()
    {
        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);

        $parameters = $kernel->getKernelParameters();

        $this->assertSame($kernel->getConfigDir(), $parameters['.kernel.config_dir']);
        $this->assertSame(['test'], $parameters['.container.known_envs']);
        $this->assertSame(['Symfony\Bundle\FrameworkBundle\FrameworkBundle' => ['all' => true]], $parameters['.kernel.bundles_definition']);
    }

    public function testGetKernelParametersWithBundlesFile()
    {
        $kernel = $this->kernel = new ConcreteMicroKernel('test', false);

        $configDir = $kernel->getConfigDir();
        mkdir($configDir, 0o777, true);

        $bundlesContent = "<?php\nreturn [\n    'Symfony\Bundle\FrameworkBundle\FrameworkBundle' => ['all' => true],\n    'TestBundle' => ['test' => true, 'dev' => true],\n];";
        file_put_contents($configDir.'/bundles.php', $bundlesContent);

        $parameters = $kernel->getKernelParameters();

        $this->assertSame(['test', 'dev'], $parameters['.container.known_envs']);
        $this->assertSame([
            'Symfony\Bundle\FrameworkBundle\FrameworkBundle' => ['all' => true],
            'TestBundle' => ['test' => true, 'dev' => true],
        ], $parameters['.kernel.bundles_definition']);
    }
}
