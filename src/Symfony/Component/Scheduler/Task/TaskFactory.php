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
final class TaskFactory implements TaskFactoryInterface
{
    private $factories;

    /**
     * @param iterable|FactoryInterface[] $factories
     */
    public function __construct(iterable $factories)
    {
        $this->factories = $factories;
    }

    public function create(array $data): TaskInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->support($data['type'])) {
                return $factory->create($data);
            }
        }

        throw new InvalidArgumentException(sprintf('No factory found for task of type %s', $options['type']));
    }
}
