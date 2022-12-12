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

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
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

require_once __DIR__.'/flex-style/src/FlexStyleMicroKernel.php';

class MicroKernelTraitTest extends TestCase
{
    private $kernel;

    protected function tearDown(): void
    {
        if ($this->kernel) {
            $kernel = $this->kernel;
            $this->kernel = null;
            $fs = new Filesystem();
            $fs->remove($kernel->getCacheDir());
        }
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
        $kernel = new FlexStyleMicroKernel('test', false);
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
                    'http_method_override' => false,
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
}

abstract class MinimalKernel extends Kernel
{
    use MicroKernelTrait;

    private $cacheDir;

    public function __construct(string $cacheDir)
    {
        parent::__construct('test', false);

        $this->cacheDir = sys_get_temp_dir().'/'.$cacheDir;
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    public function getLogDir(): string
    {
        return $this->cacheDir;
    }
}
