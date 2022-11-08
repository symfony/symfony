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

use Symfony\Component\DependencyInjection\Argument\ServiceLocatorArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;

/**
 * An attribute to set a property with tagged locator.
 *
 * @author Aleksey Polyvanyi <aleksey.polyvanyi@eonx.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class AutoconfigurePropertyTaggedLocator extends Autoconfigure
{
    public function __construct(
        string $name,
        string $tag,
        ?string $indexAttribute = null,
        ?string $defaultIndexMethod = null,
        ?string $defaultPriorityMethod = null,
        string|array $exclude = [],
    )
    {
        parent::__construct(
            properties: [
                $name => new ServiceLocatorArgument(new TaggedIteratorArgument($tag, $indexAttribute, $defaultIndexMethod, true, $defaultPriorityMethod, $exclude)),
            ]
        );
    }
}
