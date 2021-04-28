<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class KernelEventTest extends TestCase
{

    public function testIsMainRequestTrue()
    {
        $kernelEvent = new KernelEvent(new TestHttpKernel(), new Request(), HttpKernelInterface::MAIN_REQUEST);
        self::assertTrue($kernelEvent->isMainRequest());
    }

    public function testIsMainRequestFalse()
    {
        $kernelEvent = new KernelEvent(new TestHttpKernel(), new Request(), HttpKernelInterface::SUB_REQUEST);
        self::assertFalse($kernelEvent->isMainRequest());
    }

    public function testGetRequestType()
    {
        $kernelEvent = new KernelEvent(new TestHttpKernel(), new Request(), HttpKernelInterface::MAIN_REQUEST);
        self::assertSame(HttpKernelInterface::MAIN_REQUEST, $kernelEvent->getRequestType());
    }
}
