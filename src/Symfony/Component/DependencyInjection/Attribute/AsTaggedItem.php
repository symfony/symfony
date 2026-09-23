<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

/**
 * An attribute to set a service's index and order in tagged iterators/locators.
 *
 * Priority sets the initial order, with higher values first. Both "before" and
 * "after" take precedence when their target service is in the collection.
 * Missing targets are ignored. A cycle among relative constraints causes an exception.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsTaggedItem
{
    /**
     * @param string|null     $index    The index at which the service will be found when consuming tagged iterators/locators
     * @param int|null        $priority The priority of the service in iterators/locators
     * @param string|string[] $before   Service ids this service should precede in iterators/locators
     * @param string|string[] $after    Service ids this service should follow in iterators/locators
     */
    public function __construct(
        public ?string $index = null,
        public ?int $priority = null,
        public string|array $before = [],
        public string|array $after = [],
    ) {
    }
}
