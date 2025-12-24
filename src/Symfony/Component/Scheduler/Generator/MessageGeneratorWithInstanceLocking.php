<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Generator;

use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\NullStore;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\MessageProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

final class MessageGeneratorWithInstanceLocking implements MessageGeneratorInterface
{
    private ?Schedule $schedule = null;
    private ?TriggerHeap $triggerHeap = null;
    private ?\DateTimeImmutable $waitUntil;
    private ?LockFactory $lockFactory = null;
    private ?CacheInterface $cache = null;

    public function __construct(
        private readonly ScheduleProviderInterface $scheduleProvider,
        private readonly string $name,
        private readonly ClockInterface $clock = new Clock(),
    ) {
        $this->waitUntil = new \DateTimeImmutable('@0');
    }

    /**
     * @return \Generator<MessageContext, object>
     */
    public function getMessages(): \Generator
    {
        $schedule = $this->getSchedule();

        if ($schedule->shouldRestart()) {
            $this->triggerHeap = null;
            $this->waitUntil = new \DateTimeImmutable('@0');
            $schedule->setRestart(false);
        }

        if (!$this->waitUntil
            || $this->waitUntil > ($now = $this->clock->now())
        ) {
            return;
        }

        $lockFactory = $this->getLockFactory();
        $cache = $this->getCache();

        $triggerHeap = $this->triggerHeap($now);
        while (!$triggerHeap->isEmpty() && $triggerHeap->top()[0] <= $now) {
            /**
             * @var \DateTimeImmutable $triggeredAt
             * @var int                $index
             * @var RecurringMessage   $recurringMessage
             */
            [$triggeredAt, $index, $recurringMessage] = $triggerHeap->extract();
            $trigger = $recurringMessage->getTrigger();

            $messageResourceId = $this->getMessageResourceId($recurringMessage);
            $messageLock = $lockFactory->createLock($messageResourceId);
            try {
                if (!$messageLock->acquire(true)) {
                    throw new \RuntimeException('Failed to acquire message lock.');
                }

                $cacheRecalculated = false;
                /** @var \DateTimeImmutable|null $cachedNextTriggerAt */
                $cachedNextTriggerAt = $cache->get($messageResourceId, static function () use (&$cacheRecalculated) {
                    $cacheRecalculated = true;

                    // The return value is unused, so its value does not matter
                    return null;
                });
                // If the current trigger-at does not match the cached next-trigger-at, then that means the current
                // trigger-at was already handled.
                if (!$cacheRecalculated && $triggeredAt != $cachedNextTriggerAt) {
                    if ($cachedNextTriggerAt) {
                        $triggerHeap->insert([$cachedNextTriggerAt, $index, $recurringMessage]);
                    }
                    continue;
                }

                $nextTriggerAt = $trigger->getNextRunDate($triggeredAt);
                if ($schedule->shouldProcessOnlyLastMissedRun()) {
                    while ($nextTriggerAt && $nextTriggerAt < $this->clock->now()) {
                        $nextTriggerAt = $trigger->getNextRunDate($nextTriggerAt);
                    }
                }
                if ($nextTriggerAt) {
                    $triggerHeap->insert([$nextTriggerAt, $index, $recurringMessage]);
                }
                $cache->get($messageResourceId, static fn () => $nextTriggerAt, \INF);
            } finally {
                $messageLock->release();
            }

            $context = new MessageContext($this->name, $recurringMessage->getId(), $trigger, $triggeredAt, $nextTriggerAt);
            foreach ($recurringMessage->getMessages($context) as $message) {
                yield $context => $message;
            }
        }

        $this->waitUntil = $triggerHeap->isEmpty() ? null : $triggerHeap->top()[0];
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= $this->scheduleProvider->getSchedule();
    }

    private function triggerHeap(\DateTimeImmutable $time): TriggerHeap
    {
        if (null !== $this->triggerHeap) {
            return $this->triggerHeap;
        }

        $lockFactory = $this->getLockFactory();
        $cache = $this->getCache();

        $triggerHeap = new TriggerHeap($time);
        foreach ($this->getSchedule()->getRecurringMessages() as $index => $recurringMessage) {
            $trigger = $recurringMessage->getTrigger();

            $messageResourceId = $this->getMessageResourceId($recurringMessage);
            $messageLock = $lockFactory->createLock($messageResourceId);
            try {
                if (!$messageLock->acquire(true)) {
                    throw new \RuntimeException('Failed to acquire message lock.');
                }
                /** @var \DateTimeImmutable|null $cachedNextTriggerAt */
                $cachedNextTriggerAt = $cache->get($messageResourceId, static fn () => $trigger->getNextRunDate($time));
                if (!$cachedNextTriggerAt) {
                    continue;
                }
                $triggerHeap->insert([$cachedNextTriggerAt, $index, $recurringMessage]);
            } finally {
                $messageLock->release();
            }
        }

        return $this->triggerHeap = $triggerHeap;
    }

    private function getMessageResourceId(MessageProviderInterface $messageProvider): string
    {
        return rawurlencode(\sprintf('scheduler_%s_message_%s', $this->name, $messageProvider->getId()));
    }

    private function getLockFactory(): LockFactory
    {
        return $this->lockFactory ??= ($this->getSchedule()->getLockFactory() ?? new LockFactory(new NullStore()));
    }

    private function getCache(): CacheInterface
    {
        return $this->cache ??= ($this->getSchedule()->getState() ?? new NullAdapter());
    }
}
