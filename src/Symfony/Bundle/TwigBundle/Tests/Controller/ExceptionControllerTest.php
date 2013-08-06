<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\Controller;

use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Bundle\TwigBundle\Controller\ExceptionController;
use Symfony\Component\HttpFoundation\Request;

class ExceptionControllerTest extends TestCase
{
    public function testOnlyClearOwnOutputBuffers()
    {
        $flatten = $this->getMock('Symfony\Component\HttpKernel\Exception\FlattenException');
        $flatten
            ->expects($this->once())
            ->method('getStatusCode')
            ->will($this->returnValue(404));
        $twig = $this->getMockBuilder('\Twig_Environment')
            ->disableOriginalConstructor()
            ->getMock();
        $twig
            ->expects($this->any())
            ->method('render')
            ->will($this->returnValue($this->getMock('Symfony\Component\HttpFoundation\Response')));
        $twig
            ->expects($this->any())
            ->method('getLoader')
            ->will($this->returnValue($this->getMock('\Twig_LoaderInterface')));
        $request = Request::create('/');
        $request->headers->set('X-Php-Ob-Level', 1);

        $controller = new ExceptionController($twig, false);
        $controller->showAction($request, $flatten);
    }
}
