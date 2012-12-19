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

use Symfony\Bundle\FrameworkBundle\Controller\InternalController;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;

class InternalControllerTest extends TestCase
{
    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage A Controller class name must end with Controller.
     */
    public function testWithAClassMethodController()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');

        $controller = new InternalController();
        $controller->setContainer($container);

        $controller->indexAction('/', 'Symfony\Component\HttpFoundation\Request::getPathInfo');
    }

    /**
     * @expectedException        \RuntimeException
     * @expectedExceptionMessage A Controller class name must end with Controller.
     */
    public function testWithAServiceController()
    {
        $container = $this->getMock('Symfony\Component\DependencyInjection\ContainerInterface');
        $container
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue(new Request()))
        ;

        $controller = new InternalController();
        $controller->setContainer($container);

        $controller->indexAction('/', 'service:method');
    }
}
