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

use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;

/**
 * Autowires an iterator of services based on a tag name.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class AutowireIterator extends Autowire
{
    /**
     * @see ServiceSubscriberInterface::getSubscribedServices()
     *
     * @param string               $tag                   A tag name to search for to populate the iterator
     * @param string|null          $indexAttribute        The name of the attribute that defines the key referencing each service in the tagged collection
     * @param string|null          $defaultIndexMethod    The static method that should be called to get each service's key when their tag doesn't define the previous attribute
     * @param string|null          $defaultPriorityMethod The static method that should be called to get each service's priority when their tag doesn't define the "priority" attribute
     * @param string|array<string> $exclude               A service id or a list of service ids to exclude
     * @param bool                 $excludeSelf           Whether to automatically exclude the referencing service from the iterator
     */
    public function __construct(
        string $tag,
        ?string $indexAttribute = null,
        ?string $defaultIndexMethod = null,
        ?string $defaultPriorityMethod = null,
        string|array $exclude = [],
        bool $excludeSelf = true,
    ) {
        parent::__construct(new TaggedIteratorArgument($tag, $indexAttribute, $defaultIndexMethod, false, $defaultPriorityMethod, (array) $exclude, $excludeSelf));
    }
}
