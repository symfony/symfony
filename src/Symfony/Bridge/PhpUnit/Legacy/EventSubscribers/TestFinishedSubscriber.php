<?php declare(strict_types=1);

namespace Symfony\Bridge\PhpUnit\Legacy\EventSubscribers;

use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;

/**
 * @internal
 */
final class TestFinishedSubscriber extends SubscriberBase implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $this->collector()->onTestFinished($event);
    }
}
