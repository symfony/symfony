<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpKernel\Fragment\FragmentRendererInterface;

class FragmentHandlerTest extends TestCase
{
    private $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->requestStack
            ->expects($this->any())
            ->method('getCurrentRequest')
            ->willReturn(Request::create('/'))
        ;
    }

    public function testRenderWhenRendererDoesNotExist()
    {
        $this->expectException(\InvalidArgumentException::class);
        $handler = new FragmentHandler($this->requestStack);
        $handler->render('/', 'foo');
    }

    public function testRenderWithUnknownRenderer()
    {
        $this->expectException(\InvalidArgumentException::class);
        $handler = $this->getHandler($this->returnValue(new Response('foo')));

        $handler->render('/', 'bar');
    }

    public function testDeliverWithUnsuccessfulResponse()
    {
        $handler = $this->getHandler($this->returnValue(new Response('foo', 404)));
        try {
            $handler->render('/', 'foo');
            $this->fail('->render() throws a \RuntimeException exception if response is not successful');
        } catch (\Exception $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e);
            $this->assertEquals(0, $e->getCode());
            $this->assertEquals('Error when rendering "http://localhost/" (Status code is 404).', $e->getMessage());

            $previousException = $e->getPrevious();
            $this->assertInstanceOf(HttpException::class, $previousException);
            $this->assertEquals(404, $previousException->getStatusCode());
            $this->assertEquals(0, $previousException->getCode());
        }
    }

    public function testRender()
    {
        $expectedRequest = Request::create('/');
        $handler = $this->getHandler(
            $this->returnValue(new Response('foo')),
            [
                '/',
                $this->callback(function (Request $request) use ($expectedRequest) {
                    $expectedRequest->server->remove('REQUEST_TIME_FLOAT');
                    $request->server->remove('REQUEST_TIME_FLOAT');

                    return $expectedRequest == $request;
                }),
                ['foo' => 'foo', 'ignore_errors' => true],
            ]
        );

        $this->assertEquals('foo', $handler->render('/', 'foo', ['foo' => 'foo']));
    }

    protected function getHandler($returnValue, $arguments = [])
    {
        $renderer = $this->createMock(FragmentRendererInterface::class);
        $renderer
            ->expects($this->any())
            ->method('getName')
            ->willReturn('foo')
        ;
        $e = $renderer
            ->expects($this->any())
            ->method('render')
            ->will($returnValue)
        ;

        if ($arguments) {
            $e->with(...$arguments);
        }

        $handler = new FragmentHandler($this->requestStack);
        $handler->addRenderer($renderer);

        return $handler;
    }
}
