<?php declare(strict_types=1);

namespace Symfony\Bridge\PhpUnit\Legacy\EventSubscribers;

use PHPUnit\Event\Test\DeprecationTriggered;
use PHPUnit\Event\Test\DeprecationTriggeredSubscriber as BaseInterface;

/**
 * @internal
 */
final class DeprecationTriggeredSubscriber extends SubscriberBase implements BaseInterface
{
    public function notify(DeprecationTriggered $event): void
    {
        $this->collector()->onTriggeredDeprecation(\E_USER_DEPRECATED, $event);
    }
}
