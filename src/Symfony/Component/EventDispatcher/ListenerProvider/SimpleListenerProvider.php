<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\ListenerProvider;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * A minimal listener provider that always returns all configured listeners.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
final class SimpleListenerProvider implements ListenerProviderInterface
{
    private $listeners;

    /**
     * @param iterable|callable[] $listeners
     */
    public function __construct(iterable $listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * {@inheritdoc}
     */
    public function getListenersForEvent(object $event): iterable
    {
        return $this->listeners;
    }
}
