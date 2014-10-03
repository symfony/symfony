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

    public function testErrorPagesInDebugMode()
    {
        $twig = new \Twig_Environment(
            new \Twig_Loader_Array(array(
                'TwigBundle:Exception:error404.html.twig' => '
                    {%- if exception is defined and status_text is defined and status_code is defined -%}
                        OK
                    {%- else -%}
                        "exception" variable is missing
                    {%- endif -%}
                ',
            ))
        );

        $request = Request::create('whatever');

        $controller = new ExceptionController($twig, /* "debug" set to --> */ true);
        $response = $controller->testErrorPageAction($request, 404);

        $this->assertEquals(200, $response->getStatusCode()); // successful request
        $this->assertEquals('OK', $response->getContent());  // content of the error404.html template
    }

    public function testFallbackToHtmlIfNoTemplateForRequestedFormat()
    {
        $twig = new \Twig_Environment(
            new \Twig_Loader_Array(array(
                'TwigBundle:Exception:error.html.twig' => 'html',
            ))
        );

        $request = Request::create('whatever');
        $request->setRequestFormat('txt');

        $controller = new ExceptionController($twig, false);
        $response = $controller->testErrorPageAction($request, 42);

        $this->assertEquals('html', $request->getRequestFormat());
    }
}
