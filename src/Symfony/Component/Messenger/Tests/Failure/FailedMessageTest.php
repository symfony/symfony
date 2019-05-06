<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Failure;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Failure\FailedMessage;

class FailedMessageTest extends TestCase
{
    public function testGetters()
    {
        $envelope = new Envelope(new \stdClass());
        $flattenException = new FlattenException();
        $stamp = new FailedMessage(
            $envelope,
            'exception message',
            $flattenException
        );
        $this->assertSame($envelope, $stamp->getFailedEnvelope());
        $this->assertSame('exception message', $stamp->getExceptionMessage());
        $this->assertSame($flattenException, $stamp->getFlattenException());
        $this->assertInstanceOf(\DateTimeInterface::class, $stamp->getFailedAt());
    }
}
