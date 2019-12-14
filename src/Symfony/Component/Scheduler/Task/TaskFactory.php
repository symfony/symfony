<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Task;

use Symfony\Component\Scheduler\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskFactory
{
    private $factories;

    /**
     * @param iterable|TaskFactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function create(array $options): TaskInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->support($options['type'])) {
                return $factory->create($options);
            }
        }

        throw new InvalidArgumentException(sprintf('No factory found for task of type %s', $options['type']));
    }
}
