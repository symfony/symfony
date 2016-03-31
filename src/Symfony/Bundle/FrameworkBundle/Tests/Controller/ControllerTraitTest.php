<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\ControllerTrait;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

class ControllerTraitTest extends TestCase
{
    public function testAddFlash()
    {
        $flashBag = new FlashBag();
        $session = $this->getMock('Symfony\Component\HttpFoundation\Session\Session');
        $session->expects($this->once())->method('getFlashBag')->willReturn($flashBag);

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($session));

        $controller = new TestController();
        $controller->setContainer($container);
        $controller->addFlash('foo', 'bar');

        $this->assertSame(array('bar'), $flashBag->get('foo'));
    }

    public function testCreateAccessDeniedException()
    {
        $controller = new TestController();

        $this->assertInstanceOf('Symfony\Component\Security\Core\Exception\AccessDeniedException', $controller->createAccessDeniedException());
    }

    public function testRedirect()
    {
        $controller = new TestController();
        $response = $controller->redirect('http://dunglas.fr', 301);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\RedirectResponse', $response);
        $this->assertSame('http://dunglas.fr', $response->getTargetUrl());
        $this->assertSame(301, $response->getStatusCode());
    }

    public function testCreateNotFoundException()
    {
        $controller = new TestController();

        $this->assertInstanceOf('Symfony\Component\HttpKernel\Exception\NotFoundHttpException', $controller->createNotFoundException());
    }

    public function testGetDoctrine()
    {
        $doctrine = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');

        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container->expects($this->at(0))->method('has')->will($this->returnValue(true));
        $container->expects($this->at(1))->method('get')->will($this->returnValue($doctrine));

        $controller = new TestController();
        $controller->setContainer($container);

        $this->assertEquals($doctrine, $controller->getDoctrine());
    }
}

class TestController implements ContainerAwareInterface
{
    use ControllerTrait {
        redirect as public;
        addFlash as public;
        createNotFoundException as public;
        createAccessDeniedException as public;
        getDoctrine as public;
    }
}
