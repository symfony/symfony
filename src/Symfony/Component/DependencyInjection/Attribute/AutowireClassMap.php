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

use Symfony\Component\DependencyInjection\Argument\TaggedClassMapArgument;

/**
 * Autowires a map of classes based on a resource tag name.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class AutowireClassMap extends Autowire
{
    /**
     * @param string          $tag            A tag name to search for to populate the map
     * @param string|null     $indexAttribute The name of the attribute that defines the key referencing each class
     *                                        in the tagged collection; defaults to the tag's last dot-segment
     * @param string|string[] $exclude        A FQCN or a list of FQCNs to exclude
     */
    public function __construct(
        string $tag,
        ?string $indexAttribute = null,
        string|array $exclude = [],
    ) {
        parent::__construct(new TaggedClassMapArgument($tag, $indexAttribute, (array) $exclude));
    }
}
