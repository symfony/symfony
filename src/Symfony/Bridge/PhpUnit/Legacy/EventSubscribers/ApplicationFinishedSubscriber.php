<?php declare(strict_types=1);

namespace Symfony\Bridge\PhpUnit\Legacy\EventSubscribers;

use PHPUnit\Event\Application\Finished;
use PHPUnit\Event\Application\FinishedSubscriber;

/**
 * @internal
 */
final class ApplicationFinishedSubscriber extends SubscriberBase implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $this->collector()->onApplicationFinished($event);
    }
}
