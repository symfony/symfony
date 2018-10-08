<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\AmqpExt\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * @author Vincent Touzet <vincent.touzet@gmail.com>
 */
final class RoutingKeyStamp implements StampInterface
{
    private $routingKey;

    public function __construct(string $routingKey)
    {
        $this->routingKey = $routingKey;
    }

    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }
}
