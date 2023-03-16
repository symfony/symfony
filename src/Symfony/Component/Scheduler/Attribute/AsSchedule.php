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
 * Service tag to autoconfigure schedules.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsSchedule
{
    public function __construct(
        public string $name = 'default',
    ) {
    }
}
