<?php declare(strict_types=1);

namespace Symfony\Bridge\PhpUnit\Legacy\EventSubscribers;

use PHPUnit\Event\Test\PhpDeprecationTriggered;
use PHPUnit\Event\Test\PhpDeprecationTriggeredSubscriber as BaseInterface;

/**
 * @internal
 */
final class PhpDeprecationTriggeredSubscriber extends SubscriberBase implements BaseInterface
{
    public function notify(PhpDeprecationTriggered $event): void
    {
        $this->collector()->onTriggeredDeprecation(\E_DEPRECATED, $event);
    }
}
