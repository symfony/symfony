<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle\Tests\Controller;

use Symphony\Bundle\TwigBundle\Controller\PreviewErrorController;
use Symphony\Bundle\TwigBundle\Tests\TestCase;
use Symphony\Component\Debug\Exception\FlattenException;
use Symphony\Component\HttpFoundation\Response;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\HttpKernelInterface;

class PreviewErrorControllerTest extends TestCase
{
    public function testForwardRequestToConfiguredController()
    {
        $request = Request::create('whatever');
        $response = new Response('');
        $code = 123;
        $logicalControllerName = 'foo:bar:baz';

        $kernel = $this->getMockBuilder('\Symphony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Request $request) use ($logicalControllerName, $code) {
                    $this->assertEquals($logicalControllerName, $request->attributes->get('_controller'));

                    $exception = $request->attributes->get('exception');
                    $this->assertInstanceOf(FlattenException::class, $exception);
                    $this->assertEquals($code, $exception->getStatusCode());
                    $this->assertFalse($request->attributes->get('showException'));

                    return true;
                }),
                $this->equalTo(HttpKernelInterface::SUB_REQUEST)
            )
            ->will($this->returnValue($response));

        $controller = new PreviewErrorController($kernel, $logicalControllerName);

        $this->assertSame($response, $controller->previewErrorPageAction($request, $code));
    }
}
