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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PreviewErrorControllerTest extends TestCase
{
    public function testForwardRequestToConfiguredController()
    {
<<<<<<< HEAD
        $self = $this;

=======
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        $request = Request::create('whatever');
        $response = new Response("");
        $code = 123;
        $logicalControllerName = 'foo:bar:baz';

        $kernel = $this->getMock('\Symfony\Component\HttpKernel\HttpKernelInterface');
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->with(
<<<<<<< HEAD
                $this->callback(function (Request $request) use ($self, $logicalControllerName, $code) {

                    $self->assertEquals($logicalControllerName, $request->attributes->get('_controller'));

                    $exception = $request->attributes->get('exception');
                    $self->assertInstanceOf('Symfony\Component\Debug\Exception\FlattenException', $exception);
                    $self->assertEquals($code, $exception->getStatusCode());

                    $self->assertFalse($request->attributes->get('showException'));
=======
                $this->callback(function (Request $request) use ($logicalControllerName, $code) {

                    $this->assertEquals($logicalControllerName, $request->attributes->get('_controller'));

                    $exception = $request->attributes->get('exception');
                    $this->assertInstanceOf('Symfony\Component\HttpKernel\Exception\FlattenException', $exception);
                    $this->assertEquals($code, $exception->getStatusCode());
                    $this->assertFalse($request->attributes->get('showException'));
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d

                    return true;
                }),
                $this->equalTo(HttpKernelInterface::SUB_REQUEST)
            )
            ->will($this->returnValue($response));

        $controller = new PreviewErrorController($kernel, $logicalControllerName);

        $this->assertSame($response, $controller->previewErrorPageAction($request, $code));
    }
}
