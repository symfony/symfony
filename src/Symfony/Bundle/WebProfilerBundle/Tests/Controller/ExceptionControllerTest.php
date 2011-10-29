<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\Controller;

use Symfony\Bundle\WebProfilerBundle\Tests\TestCase;

use Symfony\Bundle\WebProfilerBundle\Controller\ExceptionController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\DependencyInjection\Definition;

class ExceptionControllerTest extends TestCase
{
    protected $controller;
    protected $container;
    protected $flatten;
    protected $kernel;

    protected function setUp()
    {
        parent::setUp();

        $this->flatten = $this->getMock('Symfony\Component\HttpKernel\Exception\FlattenException');
        $this->flatten->expects($this->once())->method('getStatusCode')->will($this->returnValue(404));
        $this->controller = new ExceptionController();
        $this->kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');
        $this->container = $this->getContainer();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->controller = null;
        $this->container = null;
        $this->flatten = null;
        $this->kernel = null;
    }

    /**
     * @dataProvider getDebugModes
     */
    public function testShowActionDependingOnDebug($debug)
    {
        $this->container->setParameter('kernel.debug', $debug);
        $this->controller->setContainer($this->container);
        $this->controller->showAction($this->flatten);
    }

    public function getDebugModes()
    {
        return array(
            array(true),
            array(false),
        );
    }

    private function getContainer()
    {
        $container = new ContainerBuilder();
        $container->addScope(new Scope('request'));
        $container->register('request', 'Symfony\\Component\\HttpFoundation\\Request')->setScope('request');
        $container->register('templating.helper.assets', $this->getMockClass('Symfony\\Component\\Templating\\Helper\\AssetsHelper'));
        $container->register('templating.helper.router', $this->getMockClass('Symfony\\Bundle\\FrameworkBundle\\Templating\\Helper\\RouterHelper'))
            ->addArgument(new Definition($this->getMockClass('Symfony\\Component\\Routing\\RouterInterface')));
        $container->register('twig', 'Twig_Environment');
        $container->register('templating.engine.twig',$this->getMockClass('Symfony\\Bundle\\TwigBundle\\TwigEngine'))
            ->addArgument($this->getMock('Twig_Environment'))
            ->addArgument($this->getMock('Symfony\\Component\\Templating\\TemplateNameParserInterface'))
            ->addArgument($this->getMock('Symfony\\Bundle\\FrameworkBundle\\Templating\\GlobalVariables', array(), array($this->getMock('Symfony\\Component\\DependencyInjection\\Container'))));
        $container->setAlias('templating', 'templating.engine.twig');
        $container->setParameter('kernel.bundles', array());
        $container->setParameter('kernel.cache_dir', __DIR__);
        $container->setParameter('kernel.root_dir', __DIR__);
        $container->set('kernel', $this->kernel);

        return $container;
    }
}
