<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Attribute;

use Attribute;

/**
 * Maps a tree-structured array to a nested object graph.
 *
 * Example:
 *     #[MapTree(of: CategoryDTO::class)]
 *     public array $children;
 *
 * @experimental
 *
 * @author Devoton <oton.traore@email.com>
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class MapTree
{
    /**
     * @param class-string $of
     * @param string $childrenProperty the property name that holds children
     */
    public function __construct(
        public string $of,
        public string $childrenProperty = 'children'
    ) {}
}
