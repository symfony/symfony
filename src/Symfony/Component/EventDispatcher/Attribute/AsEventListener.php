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
     * @param string|null              $event      The event name to listen to
     * @param string|null              $method     The method to run when the listened event is triggered
     * @param int|null                 $priority   The priority of this listener; null lets "before"/"after" decide it, else they only reorder within that priority
     * @param string|null              $dispatcher The service id of the event dispatcher to listen to
     * @param string|list<string>|null $before     Listeners this one runs before, as service ids, classes or "service::method"
     * @param string|list<string>|null $after      Listeners this one runs after, as service ids, classes or "service::method"
     */
    public function __construct(
        public ?string $event = null,
        public ?string $method = null,
        public ?int $priority = null,
        public ?string $dispatcher = null,
        public string|array|null $before = null,
        public string|array|null $after = null,
    ) {
    }
}
