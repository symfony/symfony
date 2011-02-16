<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\AsseticBundle\Tests\DependencyInjection;

use Symfony\Bundle\AsseticBundle\DependencyInjection\AsseticExtension;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\HttpFoundation\Request;

class AsseticExtensionTest extends \PHPUnit_Framework_TestCase
{
    private $kernel;
    private $container;

    static public function assertSaneContainer(Container $container, $message = '')
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
        if (!class_exists('Assetic\\AssetManager')) {
            $this->markTestSkipped('Assetic is not available.');
        }

        $this->kernel = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Kernel')
            ->disableOriginalConstructor()
            ->getMock();

        $this->container = new ContainerBuilder();
        $this->container->addScope(new Scope('request'));
        $this->container->register('request', 'Symfony\\Component\\HttpFoundation\\Request')->setScope('request');
        $this->container->register('response', 'Symfony\\Component\\HttpFoundation\\Response')->setScope('prototype');
        $this->container->register('twig', 'Twig_Environment');
        $this->container->setParameter('kernel.debug', false);
        $this->container->setParameter('kernel.root_dir', __DIR__);
        $this->container->setParameter('kernel.cache_dir', __DIR__);
        $this->container->setParameter('kernel.bundles', array());
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testDefaultConfig($debug)
    {
        $this->container->setParameter('kernel.debug', $debug);

        $extension = new AsseticExtension();
        $extension->load(array(array()), $this->container);

        $this->assertFalse($this->container->has('assetic.filter.yui_css'), '->load() does not load the yui_css filter when a yui value is not provided');
        $this->assertFalse($this->container->has('assetic.filter.yui_js'), '->load() does not load the yui_js filter when a yui value is not provided');

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    public function getDebugModes()
    {
        return array(
            array(true),
            array(false),
        );
    }

    public function testYuiConfig()
    {
        $extension = new AsseticExtension();
        $extension->load(array(array('yui' => '/path/to/yuicompressor.jar')), $this->container);

        $this->assertTrue($this->container->has('assetic.filter.yui_css'), '->load() loads the yui_css filter when a yui value is provided');
        $this->assertTrue($this->container->has('assetic.filter.yui_js'), '->load() loads the yui_js filter when a yui value is provided');

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    /**
     * @dataProvider getDocumentRootKeys
     */
    public function testDocumentRoot($key)
    {
        $extension = new AsseticExtension();
        $extension->load(array(array($key => '/path/to/web')), $this->container);

        $this->assertEquals('/path/to/web', $this->container->getParameter('assetic.document_root'), '"'.$key.'" sets document root');
    }

    public function getDocumentRootKeys()
    {
        return array(
            array('document_root'),
            array('document-root'),
        );
    }

    /**
     * @dataProvider getUseControllerKeys
     */
    public function testUseController($bool, $includes, $omits)
    {
        $extension = new AsseticExtension();
        $extension->load(array(array('use_controller' => $bool)), $this->container);

        foreach ($includes as $id) {
            $this->assertTrue($this->container->has($id), '"'.$id.'" is registered when use_controller is '.$bool);
        }

        foreach ($omits as $id) {
            $this->assertFalse($this->container->has($id), '"'.$id.'" is not registered when use_controller is '.$bool);
        }

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    public function getUseControllerKeys()
    {
        return array(
            array(true, array('assetic.routing_loader', 'assetic.controller'), array('assetic.asset_writer_cache_warmer', 'assetic.asset_writer')),
            array(false, array('assetic.asset_writer_cache_warmer', 'assetic.asset_writer'), array('assetic.routing_loader', 'assetic.controller')),
        );
    }

    public function testClosure()
    {
        $extension = new AsseticExtension();
        $extension->load(array(array('closure' => '/path/to/closure.jar')), $this->container);

        $this->assertSaneContainer($this->getDumpedContainer());
    }

    private function getDumpedContainer()
    {
        static $i = 0;
        $class = 'AsseticExtensionTestContainer'.$i++;

        $this->container->compile();

        $dumper = new PhpDumper($this->container);
        eval('?>'.$dumper->dump(array('class' => $class)));

        $container = new $class();
        $container->enterScope('request');
        $container->set('kernel', $this->kernel);

        return $container;
    }
}
