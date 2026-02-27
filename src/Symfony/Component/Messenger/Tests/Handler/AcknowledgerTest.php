<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Handler\Acknowledger;

class AcknowledgerTest extends TestCase
{
    public function testNackAfterAckThrowsIsAllowed()
    {
        $ackCalls = 0;
        $nackCalls = 0;
        $nackError = null;

        $ack = static function (?\Throwable $error = null) use (&$ackCalls, &$nackCalls, &$nackError): void {
            if (null === $error) {
                ++$ackCalls;
                throw new \RuntimeException('Ack failed.');
            }

            ++$nackCalls;
            $nackError = $error;
        };

        $acknowledger = new Acknowledger('handler', $ack);

        try {
            $acknowledger->ack();
        } catch (\RuntimeException) {
        }

        $error = new \RuntimeException('Nack failed.');
        $acknowledger->nack($error);

        $this->assertSame(1, $ackCalls);
        $this->assertSame(1, $nackCalls);
        $this->assertSame($error, $nackError);
        $this->assertTrue($acknowledger->isAcknowledged());
        $this->assertSame($error, $acknowledger->getError());
    }
}
