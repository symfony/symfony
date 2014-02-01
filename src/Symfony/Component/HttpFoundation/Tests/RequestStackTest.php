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

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;

/**
 * RequestStackTest
 *
 * @author Chris Heng <hengkuanyen@gmail.com>
 */
class RequestStackTest extends \PHPUnit_Framework_TestCase
{
    public function testEmptyStack()
    {
        $stack = new RequestStack();

        $this->assertSame(0, count($stack));
        $this->assertNull($stack->getCurrentRequest());
        $this->assertNull($stack->getParentRequest());
        $this->assertNull($stack->getMasterRequest());
        $this->assertNull($stack->pop());
    }

    public function testPushPop()
    {
        $request1 = new Request();
        $request2 = new Request();
        $request3 = new Request();

        $stack = new RequestStack();
        $stack->push($request1);

        $this->assertSame($request1, $stack->getCurrentRequest());
        $this->assertNull($stack->getParentRequest());
        $this->assertSame($request1, $stack->getMasterRequest());
        $this->assertSame(1, count($stack));

        $stack->push($request2);

        $this->assertSame($request2, $stack->getCurrentRequest());
        $this->assertSame($request1, $stack->getParentRequest());
        $this->assertSame($request1, $stack->getMasterRequest());
        $this->assertSame(2, count($stack));

        $stack->push($request3);

        $this->assertSame($request3, $stack->getCurrentRequest());
        $this->assertSame($request2, $stack->getParentRequest());
        $this->assertSame($request1, $stack->getMasterRequest());
        $this->assertSame(3, count($stack));

        $this->assertSame($request3, $stack->pop());

        $this->assertSame($request2, $stack->getCurrentRequest());
        $this->assertSame($request1, $stack->getParentRequest());
        $this->assertSame($request1, $stack->getMasterRequest());
        $this->assertSame(2, count($stack));

        $this->assertSame($request2, $stack->pop());
        $this->assertSame($request1, $stack->pop());
        $this->assertNull($stack->pop());
        $this->assertSame(0, count($stack));
    }
}
