<?php

namespace Symfony\Component\Scheduler\Trigger;

interface StatefulTriggerInterface extends TriggerInterface
{
    public function continue(\DateTimeImmutable $startedAt): void;
}
