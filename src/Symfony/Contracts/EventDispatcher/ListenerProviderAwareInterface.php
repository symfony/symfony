<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Contracts\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;

interface ListenerProviderAwareInterface
{
    /**
     * Registers a listener provider for an given event name.
     */
    public function setListenerProvider(string $eventName, ListenerProviderInterface $listenerProvider): void;
}
