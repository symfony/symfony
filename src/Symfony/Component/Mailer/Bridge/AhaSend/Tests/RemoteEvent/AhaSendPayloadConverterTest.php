<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\AhaSend\Tests\RemoteEvent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\AhaSend\RemoteEvent\AhaSendPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;

class AhaSendPayloadConverterTest extends TestCase
{
    public function testConvertUsesTheMessageIdHeaderAsId()
    {
        $event = (new AhaSendPayloadConverter())->convert([
            'type' => 'message.delivered',
            'timestamp' => '2024-10-27T19:37:30.928534039Z',
            'data' => [
                'recipient' => 'someone@example.com',
                'message_id_header' => 'message-id-header',
                'id' => 'ahasend-internal-id',
            ],
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame('message-id-header', $event->getId());
        $this->assertSame('someone@example.com', $event->getRecipientEmail());
    }

    #[DataProvider('provideMalformedPayloads')]
    public function testConvertThrowsOnMalformedPayload(array $data)
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The payload is malformed.');

        (new AhaSendPayloadConverter())->convert([
            'type' => 'message.delivered',
            'timestamp' => '2024-10-27T19:37:30.928534039Z',
            'data' => $data,
        ]);
    }

    public static function provideMalformedPayloads(): iterable
    {
        yield 'missing message_id_header' => [['recipient' => 'someone@example.com']];
        yield 'missing recipient' => [['message_id_header' => 'message-id-header']];
    }
}
