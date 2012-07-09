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
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Scope;

class WebProfilerExtensionTest extends TestCase
{
    private $kernel;
    /**
     * @var Symfony\Component\DependencyInjection\Container $container
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

        $this->kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');

        $this->container = new ContainerBuilder();
        $this->container->addScope(new Scope('request'));
        $this->container->register('request', 'Symfony\\Component\\HttpFoundation\\Request')->setScope('request');
        $this->container->register('templating.helper.assets', $this->getMockClass('Symfony\\Component\\Templating\\Helper\\AssetsHelper'));
        $this->container->register('templating.helper.router', $this->getMockClass('Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\RouterHelper'))
            ->addArgument(new Definition($this->getMockClass('Symfony\\Component\\Routing\\RouterInterface')));
        $this->container->register('twig', 'Twig_Environment');
        $this->container->register('templating.engine.twig', $this->getMockClass('Symfony\\Bundle\\TwigBundle\\TwigEngine'))
            ->addArgument(new Definition($this->getMockClass('Twig_Environment')))
            ->addArgument(new Definition($this->getMockClass('Symfony\\Component\\Templating\\TemplateNameParserInterface')))
            ->addArgument(new Definition($this->getMockClass('Symfony\\Bundle\\FrameworkBundle\\Templating\\GlobalVariables'), array(new Definition($this->getMockClass('Symfony\\Component\\DependencyInjection\\Container')))));
        $this->container->setParameter('kernel.bundles', array());
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.root_dir', __DIR__);
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

        $this->assertFalse($this->container->get('web_profiler.debug_toolbar')->isEnabled());

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testToolbarConfig($enabled, $verbose)
    {
        $extension = new WebProfilerExtension();
        $extension->load(array(array('toolbar' => $enabled, 'verbose' => $verbose)), $this->container);

        $this->assertSame($enabled, $this->container->get('web_profiler.debug_toolbar')->isEnabled());
        $this->assertSame($enabled && $verbose, $this->container->get('web_profiler.debug_toolbar')->isVerbose());

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    public function getDebugModes()
    {
        return array(
            array(true, true),
            array(true, false),
            array(false, false),
            array(false, true),
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
