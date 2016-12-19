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
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Scope;

class WebProfilerExtensionTest extends TestCase
{
    private $kernel;
    /**
     * @var \Symfony\Component\DependencyInjection\Container
     */
    private $container;

    public static function assertSaneContainer(Container $container, $message = '')
    {
        $errors = array();
        foreach ($container->getServiceIds() as $id) {
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
        $this->container->addScope(new Scope('request'));
        $this->container->register('request', 'Symfony\\Component\\HttpFoundation\\Request')->setScope('request');
        $this->container->register('router', $this->getMockClass('Symfony\\Component\\Routing\\RouterInterface'));
        $this->container->register('twig', 'Twig_Environment');
        $this->container->register('twig_loader', 'Twig_Loader_Array')->addArgument(array());
        $this->container->register('twig', 'Twig_Environment')->addArgument(new Reference('twig_loader'));
        $this->container->setParameter('kernel.bundles', array());
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.root_dir', __DIR__);
        $this->container->setParameter('profiler.class', array('Symfony\\Component\\HttpKernel\\Profiler\\Profiler'));
        $this->container->register('profiler', $this->getMockClass('Symfony\\Component\\HttpKernel\\Profiler\\Profiler'))
            ->addArgument(new Definition($this->getMockClass('Symfony\\Component\\HttpKernel\\Profiler\\ProfilerStorageInterface')));
        $this->container->setParameter('data_collector.templates', array());
        $this->container->set('kernel', $this->kernel);
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

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testToolbarConfig($toolbarEnabled, $interceptRedirects, $listenerInjected, $listenerEnabled)
    {
        $extension = new WebProfilerExtension();
        $extension->load(array(array('toolbar' => $toolbarEnabled, 'intercept_redirects' => $interceptRedirects)), $this->container);

        $this->assertSame($listenerInjected, $this->container->has('web_profiler.debug_toolbar'));

        if ($listenerInjected) {
            $this->assertSame($listenerEnabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        }

        $this->assertSaneContainer($this->getDumpedContainer());
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

    private function getDumpedContainer()
    {
        static $i = 0;
        $class = 'WebProfilerExtensionTestContainer'.$i++;

        $this->container->compile();

        $dumper = new PhpDumper($this->container);
        eval('?>'.$dumper->dump(array('class' => $class)));

        $container = new $class();
        $container->enterScope('request');
        $container->set('kernel', $this->kernel);

        return $container;
    }
}
