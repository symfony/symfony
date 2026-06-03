<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Transport\NullTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
class NullTransportTest extends TestCase
{
    public function testToString()
    {
        $this->assertEquals('null', (string) (new NullTransport()));
    }

    public function testSend()
    {
        $nullTransport = new NullTransport(
            $eventDispatcherMock = $this->createMock(EventDispatcherInterface::class)
        );

        $eventDispatcherMock->expects($this->exactly(2))->method('dispatch');
        $nullTransport->send(new DummyMessage());
    }
}
