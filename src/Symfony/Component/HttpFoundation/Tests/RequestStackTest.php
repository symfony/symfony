<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class RequestStackTest extends \PHPUnit_Framework_TestCase
{
    public function testGetCurrentRequest()
    {
        $requestStack = new RequestStack();
        $this->assertNull($requestStack->getCurrentRequest());

        $request = Request::create('/foo');

        $requestStack->push($request);
        $this->assertSame($request, $requestStack->getCurrentRequest());

        $this->assertSame($request, $requestStack->pop());
        $this->assertNull($requestStack->getCurrentRequest());

        $this->assertNull($requestStack->pop());
    }

    public function testGetMasterRequest()
    {
        $requestStack = new RequestStack();
        $this->assertNull($requestStack->getMasterRequest());

        $masterRequest = Request::create('/foo');
        $subRequest = Request::create('/bar');

        $requestStack->push($masterRequest);
        $requestStack->push($subRequest);

        $this->assertSame($masterRequest, $requestStack->getMasterRequest());
    }

    public function testGetParentRequest()
    {
        $requestStack = new RequestStack();
        $this->assertNull($requestStack->getParentRequest());

        $masterRequest = Request::create('/foo');

        $requestStack->push($masterRequest);
        $this->assertNull($requestStack->getParentRequest());

        $firstSubRequest = Request::create('/bar');

        $requestStack->push($firstSubRequest);
        $this->assertSame($masterRequest, $requestStack->getParentRequest());

        $secondSubRequest = Request::create('/baz');

        $requestStack->push($secondSubRequest);
        $this->assertSame($firstSubRequest, $requestStack->getParentRequest());
    }

    public function testWithRequest()
    {
        $requestStack = new RequestStack();
        $this->assertNull($requestStack->withCurrentRequest($this->expectNoCall()));
        $this->assertNull($requestStack->withMasterRequest($this->expectNoCall()));
        $this->assertNull($requestStack->withParentRequest($this->expectNoCall()));

        $masterRequest = Request::create('/foo');
        $requestStack->push($masterRequest);

        $this->assertSame('value', $requestStack->withCurrentRequest($this->expectCall($masterRequest)));
        $this->assertNull($requestStack->withParentRequest($this->expectNoCall()));
        $this->assertSame('value', $requestStack->withMasterRequest($this->expectCall($masterRequest)));

        $request = Request::create('/foo');
        $requestStack->push($request);

        $this->assertSame('value', $requestStack->withCurrentRequest($this->expectCall($request)));
        $this->assertSame('value', $requestStack->withParentRequest($this->expectCall($masterRequest)));
        $this->assertSame('value', $requestStack->withMasterRequest($this->expectCall($masterRequest)));

        $nextRequest = Request::create('/foo');
        $requestStack->push($nextRequest);

        $this->assertSame('value', $requestStack->withCurrentRequest($this->expectCall($nextRequest)));
        $this->assertSame('value', $requestStack->withParentRequest($this->expectCall($request)));
        $this->assertSame('value', $requestStack->withMasterRequest($this->expectCall($masterRequest)));
    }

    /** @expectedException InvalidArgumentException */
    public function testExceptionWithParentRequest()
    {
        $requestStack = new RequestStack();
        $requestStack->withParentRequest('invalid');
    }

    /** @expectedException InvalidArgumentException */
    public function testExceptionWithMasterRequest()
    {
        $requestStack = new RequestStack();
        $requestStack->withMasterRequest('invalid');
    }

    /** @expectedException InvalidArgumentException */
    public function testExceptionWithCurrentRequest()
    {
        $requestStack = new RequestStack();
        $requestStack->withCurrentRequest('invalid');
    }

    private function expectCall($request)
    {
        $functor = $this->getMockBuilder('stdClass')
            ->setMethods(['__invoke'])
            ->getMock();

        $functor->expects($this->once())
            ->method('__invoke')
            ->with($this->identicalTo($request))
            ->will($this->returnValue('value'));

        return $functor;
    }

    private function expectNoCall()
    {
        return static function() {
            throw new \Exception('Should not be called');
        };
    }
}
