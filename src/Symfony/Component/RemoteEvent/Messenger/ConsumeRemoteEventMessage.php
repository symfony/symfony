<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\RemoteEvent\Messenger;

use Symfony\Component\RemoteEvent\RemoteEvent;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
class ConsumeRemoteEventMessage
{
    public function __construct(
        private readonly string $type,
        private readonly RemoteEvent $event,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getEvent(): RemoteEvent
    {
        return $this->event;
    }
}
