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

/**
 * Marker interface for event listeners.
 *
 * @method void __invoke(object $event, string $eventName, EventDispatcherInterface $dispatcher)
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface EventListenerInterface
{
}
