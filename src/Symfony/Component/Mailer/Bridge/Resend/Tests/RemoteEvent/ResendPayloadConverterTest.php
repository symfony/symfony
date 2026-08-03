<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Resend\Tests\RemoteEvent;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Resend\RemoteEvent\ResendPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;

class ResendPayloadConverterTest extends TestCase
{
    private const SUPPRESSION_MESSAGE = 'Resend has suppressed sending to this address because it is on the account-level suppression list.';

    #[DataProvider('provideDroppedEvents')]
    public function testDroppedEvent(string $type, array $extraData, string $expectedReason)
    {
        $converter = new ResendPayloadConverter();

        $event = $converter->convert([
            'type' => $type,
            'created_at' => '2024-04-08T09:43:09.500Z',
            'data' => [
                'created_at' => '2024-04-08T09:43:09.438Z',
                'email_id' => '172c41ce-ba6d-4281-8a7a-541faa725748',
                'from' => 'test@resend.com',
                'to' => ['test@example.com'],
                'subject' => 'Test subject',
            ] + $extraData,
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(MailerDeliveryEvent::DROPPED, $event->getName());
        $this->assertSame('172c41ce-ba6d-4281-8a7a-541faa725748', $event->getId());
        $this->assertSame($expectedReason, $event->getReason());
        $this->assertSame('test@example.com', $event->getRecipientEmail());
    }

    public static function provideDroppedEvents(): iterable
    {
        yield 'email.failed' => [
            'email.failed',
            ['failed' => ['reason' => 'reached_daily_quota']],
            'reached_daily_quota',
        ];

        yield 'email.suppressed' => [
            'email.suppressed',
            ['suppressed' => ['message' => self::SUPPRESSION_MESSAGE, 'type' => 'OnAccountSuppressionList']],
            self::SUPPRESSION_MESSAGE,
        ];
    }

    public function testDeliveryEventWithoutFailureCarriesNoReason()
    {
        $converter = new ResendPayloadConverter();

        $event = $converter->convert([
            'type' => 'email.delivered',
            'created_at' => '2024-04-08T09:43:09.500Z',
            'data' => [
                'created_at' => '2024-04-08T09:43:09.438Z',
                'email_id' => '172c41ce-ba6d-4281-8a7a-541faa725748',
                'from' => 'test@resend.com',
                'to' => ['test@example.com'],
                'subject' => 'Test subject',
            ],
        ]);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame(MailerDeliveryEvent::DELIVERED, $event->getName());
        $this->assertSame('', $event->getReason());
    }
}
