<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Message;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;

class RedispatchMessageTest extends TestCase
{
    #[DataProvider('toStringProvider')]
    public function testToString(string|array $transportNames, string $expected)
    {
        $this->assertSame($expected, (string) new RedispatchMessage(new DummyMessage('dummy'), $transportNames));
    }

    public static function toStringProvider(): iterable
    {
        yield 'no transport' => [[], DummyMessage::class];
        yield 'empty transport' => ['', DummyMessage::class];
        yield 'one transport' => ['async', DummyMessage::class.' via async'];
        yield 'several transports' => [['async', 'failed'], DummyMessage::class.' via async, failed'];
    }

    public function testToStringOfAnEnvelope()
    {
        $this->assertSame(DummyMessage::class.' via async', (string) new RedispatchMessage(new Envelope(new DummyMessage('dummy')), 'async'));
    }
}
