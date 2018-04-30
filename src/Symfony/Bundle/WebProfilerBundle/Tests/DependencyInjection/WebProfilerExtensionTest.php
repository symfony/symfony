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

use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;
use Symfony\Bundle\WebProfilerBundle\DependencyInjection\WebProfilerExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\RegisterListenersPass;
use Symfony\Component\EventDispatcher\EventDispatcher;

class WebProfilerExtensionTest extends TestCase
{
    private $kernel;
    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    private $container;

    public static function assertSaneContainer(Container $container, $message = '', $knownPrivates = array())
    {
        $errors = array();
        $knownPrivates[] = 'debug.file_link_formatter.url_format';
        foreach ($container->getServiceIds() as $id) {
            if (in_array($id, $knownPrivates, true)) { // to be removed in 4.0
                continue;
            }
            try {
                $container->get($id);
            } catch (\Exception $e) {
                $errors[$id] = $e->getMessage();
            }
        }

        self::assertEquals(array(), $errors, $message);
    }

    protected function setUp()
    {
        parent::setUp();

        $this->kernel = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\KernelInterface')->getMock();

        $this->container = new ContainerBuilder();
        $this->container->register('event_dispatcher', EventDispatcher::class)->setPublic(true);
        $this->container->register('router', $this->getMockClass('Symfony\\Component\\Routing\\RouterInterface'))->setPublic(true);
        $this->container->register('twig', 'Twig\Environment')->setPublic(true);
        $this->container->register('twig_loader', 'Twig\Loader\ArrayLoader')->addArgument(array())->setPublic(true);
        $this->container->register('twig', 'Twig\Environment')->addArgument(new Reference('twig_loader'))->setPublic(true);
        $this->container->setParameter('kernel.bundles', array());
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.project_dir', __DIR__);
        $this->container->setParameter('kernel.charset', 'UTF-8');
        $this->container->setParameter('debug.file_link_format', null);
        $this->container->setParameter('profiler.class', array('Symfony\\Component\\HttpKernel\\Profiler\\Profiler'));
        $this->container->register('profiler', $this->getMockClass('Symfony\\Component\\HttpKernel\\Profiler\\Profiler'))
            ->setPublic(true)
            ->addArgument(new Definition($this->getMockClass('Symfony\\Component\\HttpKernel\\Profiler\\ProfilerStorageInterface')));
        $this->container->setParameter('data_collector.templates', array());
        $this->container->set('kernel', $this->kernel);
        $this->container->addCompilerPass(new RegisterListenersPass());
    }

    protected function tearDown()
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
        $extension->load(array(array()), $this->container);

        $this->assertFalse($this->container->has('web_profiler.debug_toolbar'));

        $this->assertSaneContainer($this->getCompiledContainer());
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testToolbarConfig($toolbarEnabled, $interceptRedirects, $listenerInjected, $listenerEnabled)
    {
        $extension = new WebProfilerExtension();
        $extension->load(array(array('toolbar' => $toolbarEnabled, 'intercept_redirects' => $interceptRedirects)), $this->container);

        $this->assertSame($listenerInjected, $this->container->has('web_profiler.debug_toolbar'));

        $this->assertSaneContainer($this->getCompiledContainer(), '', array('web_profiler.csp.handler'));

        if ($listenerInjected) {
            $this->assertSame($listenerEnabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        }
    }

    public function getDebugModes()
    {
        return array(
            array(false, false, false, false),
            array(true,  false, true,  true),
            array(false, true,  true,  false),
            array(true,  true,  true,  true),
        );
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
