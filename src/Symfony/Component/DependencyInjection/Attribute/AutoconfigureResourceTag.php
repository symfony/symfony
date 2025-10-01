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
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AutoconfigureResourceTag extends Autoconfigure
{
    /**
     * @param string|null  $name       The resource tag name to add
     * @param array<mixed> $attributes The attributes to attach to the resource tag
     */
    public function __construct(?string $name = null, array $attributes = [])
    {
        parent::__construct(
            resourceTags: [
                [$name ?? 0 => $attributes],
            ]
        );
    }
}
