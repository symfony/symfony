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
     * @param string          $tag            A tag name to search for to populate the iterator
     * @param string|null     $indexAttribute The name of the attribute that defines the key referencing each service in the tagged collection
     * @param string|string[] $exclude        A service id or a list of service ids to exclude
     * @param bool            $excludeSelf    Whether to automatically exclude the referencing service from the iterator
     */
    public function __construct(
        string $tag,
        ?string $indexAttribute = null,
        string|array|null $exclude = [],
        bool|string|null $excludeSelf = true,
        ...$_,
    ) {
        if (\func_num_args() > 4 || !\is_bool($excludeSelf) || null === $exclude || (\is_string($exclude) && str_starts_with($exclude, 'get') && !\array_key_exists('defaultIndexMethod', $_))) {
            [, , $defaultIndexMethod, $defaultPriorityMethod, $exclude, $excludeSelf] = \func_get_args() + [2 => null, null, [], true];
        } else {
            $defaultIndexMethod = \array_key_exists('defaultIndexMethod', $_) ? $_['defaultIndexMethod'] : false;
            $defaultPriorityMethod = \array_key_exists('defaultPriorityMethod', $_) ? $_['defaultPriorityMethod'] : false;
        }

        if (false !== $defaultIndexMethod || false !== $defaultPriorityMethod) {
            parent::__construct(new TaggedIteratorArgument($tag, $indexAttribute, $defaultIndexMethod, false, $defaultPriorityMethod, (array) $exclude, $excludeSelf));

            return;
        }

        parent::__construct(new TaggedIteratorArgument($tag, $indexAttribute, false, (array) $exclude, $excludeSelf));
    }
}
