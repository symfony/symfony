<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\Transport\Serialization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\IdentityStampHeaderMapper;

class IdentityStampHeaderMapperTest extends TestCase
{
    public function testToHeaders()
    {
        $envelope = new Envelope(new DummyMessage('Hello'), [
            new MessageIdStamp('old-id'),
            new MessageIdStamp('message-id'),
            new CorrelationStamp('correlation-id'),
            new CausationStamp('causation-id'),
            new BusNameStamp('command.bus'),
        ]);

        $this->assertSame([
            'message_id' => 'message-id',
            'correlation_id' => 'correlation-id',
            'causation_id' => 'causation-id',
        ], new IdentityStampHeaderMapper()->toHeaders($envelope));
    }

    public function testToHeadersWithoutIdentityStamps()
    {
        $this->assertSame([], new IdentityStampHeaderMapper()->toHeaders(new Envelope(new DummyMessage('Hello'))));
    }

    public function testToStamps()
    {
        $stamps = new IdentityStampHeaderMapper()->toStamps([
            'type' => DummyMessage::class,
            'message_id' => 'message-id',
            'correlation_id' => 'correlation-id',
            'causation_id' => 'causation-id',
        ]);

        $this->assertEquals([new MessageIdStamp('message-id'), new CorrelationStamp('correlation-id'), new CausationStamp('causation-id')], $stamps);
    }

    public function testToStampsCastsIntegers()
    {
        $this->assertEquals([new MessageIdStamp('42')], new IdentityStampHeaderMapper()->toStamps(['message_id' => 42]));
    }

    #[DataProvider('provideUnreadableValues')]
    public function testToStampsIgnoresUnreadableValues(mixed $value)
    {
        $this->assertSame([], new IdentityStampHeaderMapper()->toStamps(['message_id' => $value, 'correlation_id' => $value, 'causation_id' => $value]));
    }

    public static function provideUnreadableValues(): iterable
    {
        yield 'empty string' => [''];
        yield 'null' => [null];
        yield 'bool' => [true];
        yield 'float' => [1.5];
        yield 'array' => [['id']];
        yield 'object' => [new \stdClass()];
    }
}
