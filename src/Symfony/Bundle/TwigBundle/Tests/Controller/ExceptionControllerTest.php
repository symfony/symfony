<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Controller;

use Symfony\Bundle\TwigBundle\Tests\TestCase;

use Symfony\Bundle\TwigBundle\Controller\ExceptionController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Scope;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;

class ExceptionControllerTest extends TestCase
{
    protected $controller;
    protected $container;
    protected $flatten;
    protected $templating;
    protected $kernel;

    protected function setUp()
    {
        parent::setUp();

        $this->flatten = $this->getMock('Symfony\Component\HttpKernel\Exception\FlattenException');
        $this->flatten
            ->expects($this->once())
            ->method('getStatusCode')
            ->will($this->returnValue(404));
        $this->flatten
            ->expects($this->once())
            ->method('getHeaders')
            ->will($this->returnValue(array()));
        $this->controller = new ExceptionController();
        $this->kernel = $this->getMock('Symfony\\Component\\HttpKernel\\KernelInterface');
        $this->templating = $this->getMockBuilder('Symfony\\Bundle\\TwigBundle\\TwigEngine')
            ->disableOriginalConstructor()
            ->getMock();
        $this->templating
            ->expects($this->any())
            ->method('renderResponse')
            ->will($this->returnValue($this->getMock('Symfony\Component\HttpFoundation\Response')));
        $this->request = Request::create('/');
        $this->container = $this->getContainer();
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->controller = null;
        $this->container = null;
        $this->flatten = null;
        $this->templating = null;
        $this->kernel = null;
    }

    public function testOnlyClearOwnOutputBuffers()
    {
        $this->request->headers->set('X-Php-Ob-Level', 1);

        $this->controller->setContainer($this->container);
        $this->controller->showAction($this->flatten);
    }

    private function getContainer()
    {
        $container = new ContainerBuilder();
        $container->addScope(new Scope('request'));
        $container->set('request', $this->request);
        $container->set('templating', $this->templating);
        $container->setParameter('kernel.bundles', array());
        $container->setParameter('kernel.cache_dir', __DIR__);
        $container->setParameter('kernel.root_dir', __DIR__);
        $container->set('kernel', $this->kernel);

        return $container;
    }
}
