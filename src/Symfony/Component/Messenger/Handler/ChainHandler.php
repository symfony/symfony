<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;

/**
 * Represents a collection of message handlers.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ChainHandler
{
    /**
     * @var callable[]
     */
    private $handlers;

    /**
     * @param callable[] $handlers
     */
    public function __construct(array $handlers)
    {
        if (!$handlers) {
            throw new InvalidArgumentException('A collection of message handlers requires at least one handler.');
        }

        $this->handlers = $handlers;
    }

    public function __invoke($message)
    {
        foreach ($this->handlers as $handler) {
            $handler($message);
        }
    }
}
