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
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Tests\TestHttpKernel;

class GetResponseForExceptionEventTest extends TestCase
{
    public function testAllowSuccessfulResponseIsFalseByDefault()
    {
        $event = new GetResponseForExceptionEvent(new TestHttpKernel(), new Request(), 1, new \Exception());

        $this->assertFalse($event->isAllowingCustomResponseCode());
    }
}
