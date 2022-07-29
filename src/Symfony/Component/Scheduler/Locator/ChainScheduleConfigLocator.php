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

use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Scheduler\Schedule\ScheduleConfig;

final class ChainScheduleConfigLocator implements ScheduleConfigLocatorInterface
{
    /**
     * @var ScheduleConfigLocatorInterface[]
     */
    private array $locators;

    private array $lastFound = [];

    /**
     * @param iterable<ScheduleConfigLocatorInterface> $locators
     */
    public function __construct(iterable $locators)
    {
        $this->locators = (static fn (ScheduleConfigLocatorInterface ...$l) => $l)(...$locators);
    }

    public function get(string $id): ScheduleConfig
    {
        if ($locator = $this->findLocator($id)) {
            return $locator->get($id);
        }

        throw new class(sprintf('You have requested a non-existent schedule "%s".', $id)) extends \InvalidArgumentException implements NotFoundExceptionInterface { };
    }

    public function has(string $id): bool
    {
        return null !== $this->findLocator($id);
    }

    private function findLocator(string $id): ?ScheduleConfigLocatorInterface
    {
        if (isset($this->lastFound[$id])) {
            return $this->lastFound[$id];
        }

        foreach ($this->locators as $locator) {
            if ($locator->has($id)) {
                $this->lastFound = [$id => $locator];

                return $locator;
            }
        }

        return null;
    }
}
