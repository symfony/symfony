<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher;

/**
 * EventDispatcherAwareInterface should be implemented by classes that dispatch events.
 *
 * @author fugi <fugi@o2.pl>
 *
 * @api
 */
interface EventDispatcherAwareInterface
{
    /**
     * Sets the EventDispatcher.
     *
     * @param EventDispatcherInterface|null $eventDispatcher A EventDispatcherInterface instance or null
     *
     * @api
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher = null);
}
