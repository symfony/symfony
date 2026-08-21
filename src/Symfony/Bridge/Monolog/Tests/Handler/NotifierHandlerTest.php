<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler;

use Monolog\Level;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Handler\NotifierHandler;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\Notifier\AdminRecipientsProviderInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

class NotifierHandlerTest extends TestCase
{
    public function testHandleWithoutAdminRecipients()
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

        (new NotifierHandler($notifier))->handle(RecordFactory::create(Level::Error, 'Something went wrong'));

        $this->assertSame('Something went wrong', $notifier->notification->getSubject());
        $this->assertSame(Notification::IMPORTANCE_HIGH, $notifier->notification->getImportance());
        $this->assertSame([], $notifier->recipients);
    }

    public function testHandleBatchWithoutAdminRecipients()
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

        (new NotifierHandler($notifier))->handleBatch([
            RecordFactory::create(Level::Warning, 'Not handled'),
            RecordFactory::create(Level::Error, 'Something went wrong'),
            RecordFactory::create(Level::Critical, 'Something went very wrong'),
        ]);

        $this->assertSame('Something went very wrong', $notifier->notification->getSubject());
        $this->assertSame(Notification::IMPORTANCE_URGENT, $notifier->notification->getImportance());
        $this->assertSame([], $notifier->recipients);
    }

    public function testHandleSendsToAdminRecipients()
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

        (new NotifierHandler($notifier))->handle(RecordFactory::create(Level::Error, 'Something went wrong'));

        $this->assertSame([$recipient], $notifier->recipients);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testHandleSendsToAdminRecipientsOfANotifierThatDoesNotImplementTheInterface()
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

        (new NotifierHandler($notifier))->handle(RecordFactory::create(Level::Error, 'Something went wrong'));

        $this->assertSame([$recipient], $notifier->recipients);
    }
}
