<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * Represents a collection of services found by tag name to lazily iterate over.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class TaggedIteratorArgument extends IteratorArgument
{
    private mixed $indexAttribute = null;
    private ?string $defaultIndexMethod = null;
    private ?string $defaultPriorityMethod = null;
    private bool $needsIndexes = false;
    private array $exclude = [];
    private bool $excludeSelf = true;

    /**
     * @param string      $tag            The name of the tag identifying the target services
     * @param string|null $indexAttribute The name of the attribute that defines the key referencing each service in the tagged collection
     * @param bool        $needsIndexes   Whether indexes are required and should be generated when computing the map
     * @param string[]    $exclude        Services to exclude from the iterator
     * @param bool        $excludeSelf    Whether to automatically exclude the referencing service from the iterator
     */
    public function __construct(
        private string $tag,
        ?string $indexAttribute = null,
        bool|string|null $needsIndexes = false,
        array|bool $exclude = [],
        bool|string|null $excludeSelf = true,
    ) {
        parent::__construct([]);

        if (\func_num_args() > 5 || !\is_bool($needsIndexes) || !\is_array($exclude) || !\is_bool($excludeSelf)) {
            [, , $defaultIndexMethod, $needsIndexes, $defaultPriorityMethod, $exclude, $excludeSelf] = \func_get_args() + [2 => null, false, null, [], true];
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The $defaultIndexMethod and $defaultPriorityMethod arguments of tagged locators and iterators are deprecated, use the #[AsTaggedItem] attribute instead.');
        } else {
            $defaultIndexMethod = $defaultPriorityMethod = false;
        }

        if (null === $indexAttribute && $needsIndexes) {
            $indexAttribute = preg_match('/[^.]++$/', $tag, $m) ? $m[0] : $tag;
        }

        $this->indexAttribute = $indexAttribute;
        $this->defaultIndexMethod = $defaultIndexMethod ?: ($indexAttribute ? 'getDefault'.str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $indexAttribute))).'Name' : null);
        $this->defaultPriorityMethod = $defaultPriorityMethod ?: ($indexAttribute ? 'getDefault'.str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9\x7f-\xff]++/', ' ', $indexAttribute))).'Priority' : null);
        $this->needsIndexes = $needsIndexes;
        $this->exclude = $exclude;
        $this->excludeSelf = $excludeSelf;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getIndexAttribute(): ?string
    {
        return $this->indexAttribute;
    }

    /**
     * @deprecated since Symfony 8.1, use the #[AsTaggedItem] attribute instead of default methods
     */
    public function getDefaultIndexMethod(/* bool $triggerDeprecation = true */): ?string
    {
        if (!\func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The "%s()" method is deprecated, use the #[AsTaggedItem] attribute instead of default methods.', __METHOD__);
        }

        return $this->defaultIndexMethod;
    }

    public function needsIndexes(): bool
    {
        return $this->needsIndexes;
    }

    /**
     * @deprecated since Symfony 8.1, use the #[AsTaggedItem] attribute instead of default methods
     */
    public function getDefaultPriorityMethod(/* bool $triggerDeprecation = true */): ?string
    {
        if (!\func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/dependency-injection', '8.1', 'The "%s()" method is deprecated, use the #[AsTaggedItem] attribute instead of default methods.', __METHOD__);
        }

        return $this->defaultPriorityMethod;
    }

    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function excludeSelf(): bool
    {
        return $this->excludeSelf;
    }
}
