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
 * @author Alexander M. Turek <me@derrabus.de>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class AsEventListener
{
    /**
     * @param string|null          $event      The event name to listen to
     * @param string|null          $method     The method to run when the listened event is triggered
     * @param int                  $priority   The priority of this listener if several are declared for the same event
     * @param string|null          $dispatcher The service id of the event dispatcher to listen to
     * @param string|string[]|null $before     Listeners this one must be placed before, as a service id, a class, or either of them suffixed by "::method" to target a single listener
     * @param string|string[]|null $after      Listeners this one must be placed after, as a service id, a class, or either of them suffixed by "::method" to target a single listener
     */
    public function __construct(
        public ?string $event = null,
        public ?string $method = null,
        public int $priority = 0,
        public ?string $dispatcher = null,
        public string|array|null $before = null,
        public string|array|null $after = null,
    ) {
    }
}
