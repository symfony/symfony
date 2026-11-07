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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;
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

    public function testTheIdHasNoAngleBrackets()
    {
        $event = (new AhaSendPayloadConverter())->convert(self::payload('<0199f1c2-7c4d-4e2a-9b1e-2f3a4b5c6d7e@example.com>'));

        $this->assertSame('0199f1c2-7c4d-4e2a-9b1e-2f3a4b5c6d7e@example.com', $event->getId());
    }

    public function testTheIdMatchesTheMessageIdOfAnEmailSentOverSmtp()
    {
        $email = (new Email())->from('foo@example.com')->to('bar@example.com')->subject('An email')->text('Test email body');
        $sentMessage = new SentMessage($email, Envelope::create($email));
        $this->assertSame(1, preg_match('/^Message-ID: (<[^>]+>)\r$/m', $sentMessage->toString(), $header));

        $event = (new AhaSendPayloadConverter())->convert(self::payload($header[1]));

        $this->assertSame($sentMessage->getMessageId(), $event->getId());
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

    private static function payload(string $messageIdHeader): array
    {
        return [
            'type' => 'message.delivered',
            'timestamp' => '2024-10-27T19:37:30.928534039Z',
            'data' => [
                'recipient' => 'bar@example.com',
                'message_id_header' => $messageIdHeader,
                'id' => '0199f1c2-7c4d-4e2a-9b1e-2f3a4b5c6d7e',
            ],
        ];
    }
}
