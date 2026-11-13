<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Widget\Text;

/**
 * A node of inline content: a run of text, or an inline element like <strong>.
 *
 * @experimental
 */
final class InlineNode
{
    /**
     * @param array<string, string> $attributes
     * @param list<self>            $children
     */
    private function __construct(
        public readonly ?string $text,
        public readonly ?string $tagName,
        public readonly array $attributes = [],
        public readonly array $children = [],
    ) {
    }

    public static function text(string $text): self
    {
        return new self($text, null);
    }

    /**
     * @param array<string, string> $attributes
     * @param list<self>            $children
     */
    public static function element(string $tagName, array $attributes = [], array $children = []): self
    {
        return new self(null, strtolower($tagName), $attributes, $children);
    }

    public function isText(): bool
    {
        return null !== $this->text;
    }

    /**
     * Returns the text, without any formatting.
     */
    public function getPlainText(): string
    {
        if (null !== $this->text) {
            return $this->text;
        }
        if ('br' === $this->tagName) {
            return "\n";
        }

        return implode('', array_map(static fn (self $child) => $child->getPlainText(), $this->children));
    }
}
