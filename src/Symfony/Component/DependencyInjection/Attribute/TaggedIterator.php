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
 * Autowires an iterator of services based on a tag name.
 *
 * @deprecated since Symfony 7.1, use {@see AutowireIterator} instead.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class TaggedIterator extends AutowireIterator
{
    /**
     * @param string          $tag                   The tag to look for to populate the iterator
     * @param string|null     $indexAttribute        The name of the attribute that defines the key referencing each service in the tagged collection
     * @param string|null     $defaultIndexMethod    The static method that should be called to get each service's key when their tag doesn't define the previous attribute
     * @param string|null     $defaultPriorityMethod The static method that should be called to get each service's priority when their tag doesn't define the "priority" attribute
     * @param string|string[] $exclude               A service id or a list of service ids to exclude
     * @param bool            $excludeSelf           Whether to automatically exclude the referencing service from the iterator
     */
    public function __construct(
        public string $tag,
        public ?string $indexAttribute = null,
        public ?string $defaultIndexMethod = null,
        public ?string $defaultPriorityMethod = null,
        public string|array $exclude = [],
        public bool $excludeSelf = true,
    ) {
        trigger_deprecation('symfony/dependency-injection', '7.1', 'The "%s" attribute is deprecated, use "%s" instead.', self::class, AutowireIterator::class);

        parent::__construct($tag, $indexAttribute, $defaultIndexMethod, $defaultPriorityMethod, $exclude, $excludeSelf);
    }
}
