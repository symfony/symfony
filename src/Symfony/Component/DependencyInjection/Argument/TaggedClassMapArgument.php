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
 * Represents a map of classes found by resource tag name.
 */
final class TaggedClassMapArgument implements ArgumentInterface
{
    use ArgumentTrait;

    private array $values = [];
    private string $indexAttribute;

    /**
     * @param string      $tag            The tag name identifying the target classes
     * @param string|null $indexAttribute The name of the attribute that defines the key referencing each class in the
     *                                    tagged collection; defaults to the tag's last dot-segment
     * @param string[]    $exclude        FQCNs to exclude from the class map
     */
    public function __construct(
        private string $tag,
        ?string $indexAttribute = null,
        private array $exclude = [],
    ) {
        $this->indexAttribute = $indexAttribute ?? (preg_match('/[^.]++$/', $tag, $matches) ? $matches[0] : $tag);
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getIndexAttribute(): string
    {
        return $this->indexAttribute;
    }

    /**
     * @return string[]
     */
    public function getExclude(): array
    {
        return $this->exclude;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function setValues(array $values): void
    {
        $this->values = $values;
    }
}
