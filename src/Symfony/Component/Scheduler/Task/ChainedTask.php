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
final class ChainedTask extends AbstractTask
{
    /**
     * @var TaskInterface[]
     */
    private $tasks;

    public function __construct(string $name, array $tasks, array $options = [], array $additionalOptions = [])
    {
        $this->tasks = $tasks;

        parent::__construct($name, $options, $additionalOptions);
    }

    public function getTasks(): array
    {
        return $this->tasks;
    }
}
