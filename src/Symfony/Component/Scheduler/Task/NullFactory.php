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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullFactory implements FactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $options): TaskInterface
    {
        $name = $options['name'] ?? '';

        unset($options['name']);

        return new NullTask($name, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function support(string $type): bool
    {
        return 'null' === $type;
    }
}
