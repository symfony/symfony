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
class AsCronTask
{
    /**
     * @param string                              $expression The cron expression to define the task schedule (i.e. "5 * * * *")
     * @param string|null                         $timezone   The timezone used with the cron expression
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
        public readonly string $expression,
        public readonly ?string $timezone = null,
        public readonly ?int $jitter = null,
        public readonly array|string|null $arguments = null,
        public readonly string $schedule = 'default',
        public readonly ?string $method = null,
        public readonly array|string|null $transports = null,
    ) {
    }
}
