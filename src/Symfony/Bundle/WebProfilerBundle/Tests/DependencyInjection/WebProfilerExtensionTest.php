<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\DependencyInjection;

use Symfony\Bundle\WebProfilerBundle\DependencyInjection\WebProfilerExtension;
use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class WebProfilerExtensionTest extends TestCase
{
    private $kernel;
    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    private $container;

    public static function assertSaneContainer(Container $container)
    {
        $removedIds = $container->getRemovedIds();
        $errors = [];
        foreach ($container->getServiceIds() as $id) {
            if (isset($removedIds[$id])) {
                continue;
            }
            try {
                $container->get($id);
            } catch (\Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        self::assertSame([], $errors);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = $this->createMock(KernelInterface::class);

        $profiler = $this->createMock(Profiler::class);
        $profilerStorage = $this->createMock(ProfilerStorageInterface::class);
        $router = $this->createMock(RouterInterface::class);

        $this->container = new ContainerBuilder();
        $this->container->register('data_collector.dump', DumpDataCollector::class)->setPublic(true);
        $this->container->register('error_handler.error_renderer.html', HtmlErrorRenderer::class)->setPublic(true);
        $this->container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        $this->container->register('router', $router::class)->setPublic(true);
        $this->container->register('twig', Environment::class)->setPublic(true);
        $this->container->register('twig_loader', ArrayLoader::class)->addArgument([])->setPublic(true);
        $this->container->register('twig', Environment::class)->addArgument(new Reference('twig_loader'))->setPublic(true);
        $this->container->setParameter('kernel.bundles', []);
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.build_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.charset', 'UTF-8');
        $this->container->setParameter('debug.file_link_format', null);
        $this->container->setParameter('profiler.class', [Profiler::class]);
        $this->container->register('profiler', $profiler::class)
            ->setPublic(true)
            ->addArgument(new Definition($profilerStorage::class));
        $this->container->setParameter('data_collector.templates', []);
        $this->container->set('kernel', $this->kernel);
        $this->container->addCompilerPass(new RegisterListenersPass());
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->container = null;
        $this->kernel = null;
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testDefaultConfig($debug)
    {
        $this->container->setParameter('kernel.debug', $debug);

        $extension = new WebProfilerExtension();
        $extension->load([[]], $this->container);
        $this->container->removeDefinition('web_profiler.controller.exception');

        $this->assertFalse($this->container->has('web_profiler.debug_toolbar'));

        self::assertSaneContainer($this->getCompiledContainer());
    }

    public static function getDebugModes()
    {
        return [
            ['debug' => false],
            ['debug' => true],
        ];
    }

    /**
     * @dataProvider getToolbarConfig
     */
    public function testToolbarConfig(bool $toolbarEnabled, bool $listenerInjected, bool $listenerEnabled)
    {
        $extension = new WebProfilerExtension();
        $extension->load([['toolbar' => $toolbarEnabled]], $this->container);
        $this->container->removeDefinition('web_profiler.controller.exception');

        $this->assertSame($listenerInjected, $this->container->has('web_profiler.debug_toolbar'));

        self::assertSaneContainer($this->getCompiledContainer());

        if ($listenerInjected) {
            $this->assertSame($listenerEnabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        }
    }

    public static function getToolbarConfig()
    {
        return [
            [
                'toolbarEnabled' => false,
                'listenerInjected' => false,
                'listenerEnabled' => false,
            ],
            [
                'toolbarEnabled' => true,
                'listenerInjected' => true,
                'listenerEnabled' => true,
            ],
        ];
    }

    /**
     * @dataProvider getInterceptRedirectsToolbarConfig
     */
    public function testToolbarConfigUsingInterceptRedirects(
        bool $toolbarEnabled,
        bool $interceptRedirects,
        bool $listenerInjected,
        bool $listenerEnabled
    ) {
        $extension = new WebProfilerExtension();
        $extension->load(
            [['toolbar' => $toolbarEnabled, 'intercept_redirects' => $interceptRedirects]],
            $this->container
        );
        $this->container->removeDefinition('web_profiler.controller.exception');

        $this->assertSame($listenerInjected, $this->container->has('web_profiler.debug_toolbar'));

        self::assertSaneContainer($this->getCompiledContainer());

        if ($listenerInjected) {
            $this->assertSame($listenerEnabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        }
    }

    public static function getInterceptRedirectsToolbarConfig()
    {
        return [
             [
                 'toolbarEnabled' => false,
                 'interceptRedirects' => true,
                 'listenerInjected' => true,
                 'listenerEnabled' => false,
            ],
            [
                'toolbarEnabled' => false,
                'interceptRedirects' => false,
                'listenerInjected' => false,
                'listenerEnabled' => false,
            ],
            [
                'toolbarEnabled' => true,
                'interceptRedirects' => true,
                'listenerInjected' => true,
                'listenerEnabled' => true,
            ],
        ];
    }

    private function getCompiledContainer()
    {
        if ($this->container->has('web_profiler.debug_toolbar')) {
            $this->container->getDefinition('web_profiler.debug_toolbar')->setPublic(true);
        }
        $this->container->compile();
        $this->container->set('kernel', $this->kernel);

        return $this->container;
    }
}
