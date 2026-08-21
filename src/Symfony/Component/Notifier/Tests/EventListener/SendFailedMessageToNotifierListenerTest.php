<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests\EventListener;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Notifier\AdminRecipientsProviderInterface;
use Symfony\Component\Notifier\EventListener\SendFailedMessageToNotifierListener;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class SendFailedMessageToNotifierListenerTest extends TestCase
{
    public function testItSendsWithoutAdminRecipients()
    {
        $notifier = new class implements NotifierInterface {
            public ?Notification $notification = null;
            public array $recipients = [];

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->notification = $notification;
                $this->recipients = $recipients;
            }
        };

        (new SendFailedMessageToNotifierListener($notifier))->onMessageFailed($this->createEvent(new \RuntimeException('Boom')));

        $this->assertSame('A "stdClass" message has just failed: RuntimeException: Boom.', $notifier->notification->getSubject());
        $this->assertSame(Notification::IMPORTANCE_HIGH, $notifier->notification->getImportance());
        $this->assertSame([], $notifier->recipients);
    }

    public function testItSendsToAdminRecipients()
    {
        $notifier = new class implements NotifierInterface, AdminRecipientsProviderInterface {
            public array $adminRecipients = [];
            public array $recipients = [];

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->recipients = $recipients;
            }

            public function getAdminRecipients(): array
            {
                return $this->adminRecipients;
            }
        };
        $recipient = new Recipient('admin@example.com');
        $notifier->adminRecipients = [$recipient];

        (new SendFailedMessageToNotifierListener($notifier))->onMessageFailed($this->createEvent(new \RuntimeException('Boom')));

        $this->assertSame([$recipient], $notifier->recipients);
    }

    public function testItSendsNothingWhenTheMessageWillBeRetried()
    {
        $notifier = new class implements NotifierInterface {
            public bool $sent = false;

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->sent = true;
            }
        };

        $event = $this->createEvent(new \RuntimeException('Boom'));
        $event->setForRetry();

        (new SendFailedMessageToNotifierListener($notifier))->onMessageFailed($event);

        $this->assertFalse($notifier->sent);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testItSendsToAdminRecipientsOfANotifierThatDoesNotImplementTheInterface()
    {
        $notifier = new class implements NotifierInterface {
            public array $adminRecipients = [];
            public array $recipients = [];

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->recipients = $recipients;
            }

            public function getAdminRecipients(): array
            {
                return $this->adminRecipients;
            }
        };
        $recipient = new Recipient('admin@example.com');
        $notifier->adminRecipients = [$recipient];

        $this->expectUserDeprecationMessage(\sprintf('Since symfony/notifier 8.2: Declaring "getAdminRecipients()" on "%s" without implementing "Symfony\Component\Notifier\AdminRecipientsProviderInterface" is deprecated.', get_debug_type($notifier)));

        (new SendFailedMessageToNotifierListener($notifier))->onMessageFailed($this->createEvent(new \RuntimeException('Boom')));

        $this->assertSame([$recipient], $notifier->recipients);
    }

    private function createEvent(\Throwable $throwable): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'receiver', $throwable);
    }
}
