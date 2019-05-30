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

use Symfony\Bundle\TwigBundle\Controller\PreviewErrorController;
use Symfony\Bundle\TwigBundle\Tests\TestCase;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PreviewErrorControllerTest extends TestCase
{
    public function testForwardRequestToConfiguredController()
    {
        $request = Request::create('whatever');
        $response = new Response('');
        $code = 123;
        $logicalControllerName = 'foo:bar:baz';

        $kernel = $this->getMockBuilder('\Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
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
            ->willReturn($response);

        $controller = new PreviewErrorController($kernel, $logicalControllerName);

        $this->assertSame($response, $controller->previewErrorPageAction($request, $code));
    }
}
