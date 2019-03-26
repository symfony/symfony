<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

/**
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
class QueueNameStamp implements StampInterface
{
    private $queueName;

    public function __construct(string $queueName)
    {
        $this->queueName = $queueName;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }
}
