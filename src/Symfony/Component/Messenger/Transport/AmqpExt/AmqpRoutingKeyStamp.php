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
 *
 * @experimental in 4.3
 */
final class AmqpRoutingKeyStamp implements StampInterface
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
