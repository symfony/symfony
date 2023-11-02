<?php declare(strict_types=1);

namespace Symfony\Bridge\PhpUnit\Legacy\EventSubscribers;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;

/**
 * @internal
 */
final class TestRunnerFinishedSubscriber extends SubscriberBase implements FinishedSubscriber
{
    public function notify(Finished $event): void
    {
        $this->collector()->onTestRunnerFinished();
    }
}
