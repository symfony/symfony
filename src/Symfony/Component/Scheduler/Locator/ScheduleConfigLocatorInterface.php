<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Locator;

use Psr\Container\ContainerInterface;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;

interface ScheduleConfigLocatorInterface extends ContainerInterface
{
    public function get(string $id): ScheduleConfig;
}
