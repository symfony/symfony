<?php declare(strict_types=1);

namespace Symfony\Bridge\PhpUnit\Legacy\EventSubscribers;

use Symfony\Bridge\PhpUnit\Legacy\SymfonyTestEventsCollectorForV10_2;

/**
 * @internal
 */
abstract class SubscriberBase {

    public function __construct(private SymfonyTestEventsCollectorForV10_2 $collector)
    {
    }

    protected function collector(): SymfonyTestEventsCollectorForV10_2
    {
        return $this->collector;
    }

}
