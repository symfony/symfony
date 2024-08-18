<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\EventListener\EnvelopeListener;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

class EnvelopeListenerTest extends TestCase
{
    /**
     * @dataProvider provideRecipientsTests
     */
    public function testRecipients(array $expected, ?array $recipients = null, array $allowedRecipients = [])
    {
        $listener = new EnvelopeListener(null, $recipients, $allowedRecipients);
        $message = new RawMessage('message');
        $envelope = new Envelope(new Address('sender@example.com'), [new Address('r1@example.com'), new Address('r2@symfony.com')]);
        $event = new MessageEvent($message, $envelope, 'default');

        $listener->onMessage($event);

        $recipients = array_map(fn (Address $a): string => $a->getAddress(), $event->getEnvelope()->getRecipients());
        $this->assertSame($expected, $recipients);
    }

    public static function provideRecipientsTests(): iterable
    {
        yield [['r1@example.com', 'r2@symfony.com'], null, []];
        yield [['admin@admin.com'], ['admin@admin.com'], []];
        yield [['admin@admin.com', 'r1@example.com'], ['admin@admin.com'], ['.*@example\.com']];
        yield [['admin@admin.com', 'r1@example.com', 'r2@symfony.com'], ['admin@admin.com'], ['.*@example\.com', '.*@symfony\.com']];
        yield [['r1@example.com', 'r2@symfony.com'], ['r1@example.com'], ['.*@example\.com', '.*@symfony\.com']];
    }
}
