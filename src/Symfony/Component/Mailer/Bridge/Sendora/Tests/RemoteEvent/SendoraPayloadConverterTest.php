<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Sendora\Tests\RemoteEvent;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Bridge\Sendora\RemoteEvent\SendoraPayloadConverter;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Exception\ParseException;

class SendoraPayloadConverterTest extends TestCase
{
    public function testABounceWithoutResponseHasItsClassificationAsReason()
    {
        $payload = $this->getPayload('bounced');
        $payload['details']['response'] = null;
        $payload['details']['code'] = null;

        $event = (new SendoraPayloadConverter())->convert($payload);

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame('InvalidRecipient', $event->getReason());
    }

    public function testADeliveryHasNoReason()
    {
        $event = (new SendoraPayloadConverter())->convert($this->getPayload('delivered'));

        $this->assertInstanceOf(MailerDeliveryEvent::class, $event);
        $this->assertSame('', $event->getReason());
    }

    public function testAMessageEventWithoutItsMessageIsRefused()
    {
        $payload = $this->getPayload('opened');
        unset($payload['messageId']);

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('The payload of the "opened" event is malformed.');

        (new SendoraPayloadConverter())->convert($payload);
    }

    public function testAnInvalidDateIsRefused()
    {
        $payload = $this->getPayload('clicked');
        $payload['occurredAt'] = 'yesterday';

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid date "yesterday".');

        (new SendoraPayloadConverter())->convert($payload);
    }

    private function getPayload(string $event): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Webhook/Fixtures/'.$event.'.json'), true);
    }
}
