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
 * An attribute to tell under which index and priority a service class should be found in tagged iterators/locators.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AsTaggedItem
{
    /**
     * @param string|null $index    The index at which the service will be found when consuming tagged iterators/locators
     * @param int|null    $priority The priority of the service in iterators/locators; the higher the number, the earlier it will
     */
    public function __construct(
        public ?string $index = null,
        public ?int $priority = null,
    ) {
    }
}
