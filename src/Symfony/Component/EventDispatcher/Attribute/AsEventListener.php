<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Attribute;

/**
 * Service tag to autoconfigure event listeners.
 *
 * For listeners of the same event and dispatcher, priority sets the initial
 * order, with higher values first. Both "before" and "after" take precedence
 * when their target service listens to that event on that dispatcher. Missing
 * targets are ignored. A cycle among relative constraints causes an exception.
 * Relative constraints require symfony/dependency-injection 8.2 or later.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsEventListener
{
    /**
     * @param string|null     $event      The event name to listen to
     * @param string|null     $method     The method to run when the listened event is triggered
     * @param int             $priority   The priority of this listener if several are declared for the same event
     * @param string|null     $dispatcher The service id of the event dispatcher to listen to
     * @param string|string[] $before     Service ids of listeners to run after this listener
     * @param string|string[] $after      Service ids of listeners to run before this listener
     */
    public function __construct(
        public ?string $event = null,
        public ?string $method = null,
        public int $priority = 0,
        public ?string $dispatcher = null,
        public string|array $before = [],
        public string|array $after = [],
    ) {
    }
}
