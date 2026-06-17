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
 * An attribute to tell how a base type should be tagged.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AutoconfigureTag extends Autoconfigure
{
    /**
     * @param string|null           $name       The tag name to add
     * @param array<mixed>|\Closure $attributes The attributes to attach to the tag. To compute them per tagged
     *                                          service, pass a closure receiving the concrete class-string (requires
     *                                          PHP 8.5), or a [class-string, method] callable whose static method is
     *                                          called on each concrete class (works on PHP 8.4)
     */
    public function __construct(?string $name = null, array|\Closure $attributes = [])
    {
        parent::__construct(
            tags: [
                [$name ?? 0 => $attributes],
            ]
        );
    }
}
