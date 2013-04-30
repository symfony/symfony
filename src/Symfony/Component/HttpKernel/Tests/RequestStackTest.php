<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests;

use Symfony\Component\HttpKernel\RequestStack;
use Symfony\Component\HttpFoundation\Request;

class RequestStackTest extends \PHPUnit_Framework_TestCase
{
    public function testPushThenPopReturnsSameRequest()
    {
        $request = Request::create('/');

        $stack = new RequestStack();
        $stack->push($request);

        $this->assertSame($request, $stack->pop());
        $this->assertNull($stack->pop());
    }

    public function testPopEmptyStackReturnsNull()
    {
        $stack = new RequestStack();

        $this->assertNull($stack->pop());
    }

    public function testGetCurrentRequest()
    {
        $request = Request::create('/');
        $stack = new RequestStack();

        $this->assertNull($stack->getCurrentRequest());

        $stack->push($request);

        $this->assertSame($request, $stack->getCurrentRequest());
        $this->assertSame($request, $stack->getCurrentRequest());
    }

    public function testGetMasterRequest()
    {
        $request = Request::create('/');
        $stack = new RequestStack();

        $this->assertNull($stack->getMasterRequest());

        $stack->push($request);

        $this->assertSame($request, $stack->getMasterRequest());
        $this->assertSame($request, $stack->getMasterRequest());
    }

    public function testGetParentRequest()
    {
        $request = Request::create('/');
        $subrequest = Request::create('/');

        $stack = new RequestStack();
        $stack->push($request);
        $stack->push($subrequest);

        $this->assertSame($request, $stack->getParentRequest());
        $this->assertSame($subrequest, $stack->pop());
        $this->assertSame($request, $stack->pop());
    }
}
