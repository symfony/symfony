<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @author Guillaume Gammelin <ggammelin@gmail.com>
 * @author Samuel Roze <samuel.roze@gmail.com>
 *
 * @experimental in 4.3
 */
final class AmqpStamp implements StampInterface
{
    private $routingKey;
    private $flags;
    private $attributes;

    public function __construct(string $routingKey = null, int $flags = AMQP_NOPARAM, array $attributes = [])
    {
        $this->routingKey = $routingKey;
        $this->flags = $flags;
        $this->attributes = $attributes;
    }

    public function getRoutingKey(): ?string
    {
        return $this->routingKey;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
