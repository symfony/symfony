<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Webhook\Server;

use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Subscriber;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 6.3
 */
interface TransportInterface
{
    public function send(Subscriber $subscriber, RemoteEvent $event): void;
}
