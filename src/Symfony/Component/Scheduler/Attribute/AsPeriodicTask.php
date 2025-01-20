<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Attribute;

/**
 * A marker to call a service method from scheduler.
 *
 * @author valtzu <valtzu@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsPeriodicTask
{
    /**
     * @param string|int                          $frequency  A string (i.e. "every hour") or an integer (the number of seconds) representing the frequency of the task
     * @param string|null                         $from       A string representing the start time of the periodic task (i.e. "08:00:00")
     * @param string|null                         $until      A string representing the end time of the periodic task (i.e. "20:00:00")
     * @param int|null                            $jitter     The cron jitter, in seconds; for example, if set to 60, the cron
     *                                                        will randomly wait for a number of seconds between 0 and 60 before
     *                                                        executing which allows to avoid load spikes that can happen when many tasks
     *                                                        run at the same time
     * @param array<array-key, mixed>|string|null $arguments  The arguments to pass to the cron task
     * @param string                              $schedule   The name of the schedule responsible for triggering the task
     * @param string|null                         $method     The method to run as the task when the attribute target is a class
     * @param string[]|string|null                $transports One or many transports through which the message scheduling the task will go
     */
    public function __construct(
        public readonly string|int $frequency,
        public readonly ?string $from = null,
        public readonly ?string $until = null,
        public readonly ?int $jitter = null,
        public readonly array|string|null $arguments = null,
        public readonly string $schedule = 'default',
        public readonly ?string $method = null,
        public readonly array|string|null $transports = null,
    ) {
    }
}
