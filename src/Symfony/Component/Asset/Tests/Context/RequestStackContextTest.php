<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\Context;

use Symfony\Component\Asset\Context\RequestStackContext;

class RequestStackContextTest extends \PHPUnit_Framework_TestCase
{
    public function testItIsAContext()
    {
        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')
            ->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertInstanceOf('Symfony\Component\Asset\Context\ContextInterface', $requestStackContext);
    }

    public function testGetBasePath()
    {
        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')
            ->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEmpty($requestStackContext->getBasePath());

        $testBasePath = 'test-path';

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->getMock();
        $request->method('getBasePath')
            ->willReturn($testBasePath);
        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')
            ->getMock();
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertEquals($testBasePath, $requestStackContext->getBasePath());
    }

    public function testIsSecure()
    {
        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')
            ->getMock();
        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertFalse($requestStackContext->isSecure());

        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')
            ->getMock();
        $request->method('isSecure')
            ->willReturn(true);
        $requestStack = $this->getMockBuilder('Symfony\Component\HttpFoundation\RequestStack')
            ->getMock();
        $requestStack->method('getMasterRequest')
            ->willReturn($request);

        $requestStackContext = new RequestStackContext($requestStack);

        $this->assertTrue($requestStackContext->isSecure());
    }
}
