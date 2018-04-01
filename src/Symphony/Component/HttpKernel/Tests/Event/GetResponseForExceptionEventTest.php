<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Tests\Event;

use PHPUnit\Framework\TestCase;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symphony\Component\HttpKernel\Tests\TestHttpKernel;

class GetResponseForExceptionEventTest extends TestCase
{
    public function testAllowSuccessfulResponseIsFalseByDefault()
    {
        $event = new GetResponseForExceptionEvent(new TestHttpKernel(), new Request(), 1, new \Exception());

        $this->assertFalse($event->isAllowingCustomResponseCode());
    }
}
