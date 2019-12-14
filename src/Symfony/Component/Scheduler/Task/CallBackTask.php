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
final class CallBackTask extends AbstractTask
{
    /**
     * @var string|callable
     */
    private $callback;

    /**
     * @var array
     */
    private $arguments;

    /**
     * {@inheritdoc}
     */
    public function __construct(string $name, $callback, array $arguments = [], array $options = [], array $additionalOptions = [])
    {
        if (!\is_string($callback) && !\is_callable($callback)) {
            throw new \InvalidArgumentException('The given callback is not a valid callable, must be a string or a callable!');
        }

        $this->callback = $callback;
        $this->arguments = $arguments;

        parent::__construct($name, $options, $additionalOptions);
    }

    /**
     * @return callable|string
     */
    public function getCallback()
    {
        return $this->callback;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }
}
