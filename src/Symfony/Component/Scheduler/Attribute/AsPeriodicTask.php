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
