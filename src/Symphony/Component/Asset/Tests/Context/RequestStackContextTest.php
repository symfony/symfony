<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Tests\Context;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Asset\Context\RequestStackContext;

class RequestStackContextTest extends TestCase
{
    public function testGetBasePathEmpty()
    {
        $requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEmpty($requestStackContext->getBasePath());
    }

    public function testGetBasePathSet()
    {
        $testBasePath = 'test-path';

        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEquals($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecureFalse()
    {
        $requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertFalse($requestStackContext->isSecure());
    }

    public function testIsSecureTrue()
    {
        $request = $this->getMockBuilder('Symphony\Component\HttpFoundation\Request')->getMock();
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertTrue($requestStackContext->isSecure());
    }

    public function testDefaultContext()
    {
        $requestStack = $this->getMockBuilder('Symphony\Component\HttpFoundation\RequestStack')->getMock();
        $requestStackContext = new RequestStackContext($requestStack, 'default-path', true);

        $this->assertSame('default-path', $requestStackContext->getBasePath());
        $this->assertTrue($requestStackContext->isSecure());
    }
}
