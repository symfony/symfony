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

class WebProfilerExtensionTest extends TestCase
{
    private $kernel;
    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    private $container;

    public static function assertSaneContainer(Container $container, $message = '', $knownPrivates = [])
    {
        $errors = [];
        $knownPrivates[] = 'debug.file_link_formatter.url_format';
        foreach ($container->getServiceIds() as $id) {
            if (\in_array($id, $knownPrivates, true)) { // for BC with 3.4
                continue;
            }
            try {
                $container->get($id);
            } catch (\Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        self::assertEquals([], $errors, $message);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->kernel = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\KernelInterface')->getMock();

        $this->container = new ContainerBuilder();
        $this->container->register('error_handler.error_renderer.html', HtmlErrorRenderer::class)->setPublic(true);
        $this->container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        $this->container->register('router', $this->getMockClass('Symfony\\Component\\Routing\\RouterInterface'))->setPublic(true);
        $this->container->register('twig', 'Twig\Environment')->setPublic(true);
        $this->container->register('twig_loader', 'Twig\Loader\ArrayLoader')->addArgument([])->setPublic(true);
        $this->container->register('twig', 'Twig\Environment')->addArgument(new Reference('twig_loader'))->setPublic(true);
        $this->container->setParameter('kernel.bundles', []);
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.charset', 'UTF-8');
        $this->container->setParameter('debug.file_link_format', null);
        $this->container->setParameter('profiler.class', ['Symfony\\Component\\HttpKernel\\Profiler\\Profiler']);
        $this->container->register('profiler', $this->getMockClass('Symfony\\Component\\HttpKernel\\Profiler\\Profiler'))
            ->setPublic(true)
            ->addArgument(new Definition($this->getMockClass('Symfony\\Component\\HttpKernel\\Profiler\\ProfilerStorageInterface')));
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

    public function getDebugModes()
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

        self::assertSaneContainer($this->getCompiledContainer(), '', ['web_profiler.csp.handler']);

        if ($listenerInjected) {
            $this->assertSame($listenerEnabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        }
    }

    public function getToolbarConfig()
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
     * @group legacy
     *
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

        self::assertSaneContainer($this->getCompiledContainer(), '', ['web_profiler.csp.handler']);

        if ($listenerInjected) {
            $this->assertSame($listenerEnabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        }
    }

    public function getInterceptRedirectsToolbarConfig()
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
