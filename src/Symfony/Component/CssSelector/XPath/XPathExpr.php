<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\XPath;

/**
 * XPath expression translator interface.
 *
 * This component is a port of the Python cssselect library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @internal
 */
class XPathExpr
{
    public function __construct(
        private string $path = '',
        private string $element = '*',
        private string $condition = '',
        bool $starPrefix = false,
    ) {
        if ($starPrefix) {
            $this->addStarPrefix();
        }
    }

    public function getElement(): string
    {
        return $this->element;
    }

    /**
     * @return $this
     */
    public function addCondition(string $condition, string $operator = 'and'): static
    {
        $this->condition = '' !== $this->condition ? \sprintf('(%s) %s (%s)', $this->condition, $operator, $condition) : $condition;

        return $this;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    /**
     * @return $this
     */
    public function addNameTest(): static
    {
        if ('*' !== $this->element) {
            if ('*/' === $this->path) {
                // after the name test, position() and last() would count the siblings of any type
                $this->condition = self::positionsToSiblings($this->condition, $this->element);
            }

            // name() returns the prefix used by the document, which may not be the prefix of the selector
            $this->addCondition(str_contains($this->element, ':') ? 'self::'.$this->element : 'name() = '.Translator::getXpathLiteral($this->element));
            $this->element = '*';
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function addStarPrefix(): static
    {
        if ('*/' !== $this->path) {
            $this->path .= '*/';
        }

        return $this;
    }

    /**
     * Joins another XPathExpr with a combiner.
     *
     * @return $this
     */
    public function join(string $combiner, self $expr): static
    {
        $path = $this->__toString().$combiner;

        if ('*/' !== $expr->path) {
            $path .= $expr->path;
        }

        $this->path = $path;
        $this->element = $expr->element;
        $this->condition = $expr->condition;

        return $this;
    }

    /**
     * Replaces position() and last() by tests on the siblings of the context node.
     */
    public static function positionsToSiblings(string $condition, string $element): string
    {
        // [1] stops at the first sibling found: count() is used only when An+B needs it
        $first = \sprintf('not(preceding-sibling::%s[1])', $element);
        $last = \sprintf('not(following-sibling::%s[1])', $element);
        $depth = 0;
        $result = '';

        foreach (preg_split('/(\'[^\']*+\'|"[^"]*+"|[\[\]]|position\(\) = (?:1|last\(\)(?: - 0)?)(?![\d-]| -)|last\(\) = 1(?!\d)|last\(\) - position\(\)|position\(\)|last\(\))/', $condition, -1, \PREG_SPLIT_DELIM_CAPTURE) as $part) {
            if ('[' === $part) {
                ++$depth;
            } elseif (']' === $part) {
                --$depth;
            } elseif (!$depth) {
                $part = match ($part) {
                    'position() = 1' => $first,
                    'position() = last()', 'position() = last() - 0' => $last,
                    'last() = 1' => $first.' and '.$last,
                    'last() - position()' => \sprintf('count(following-sibling::%s)', $element),
                    'position()' => \sprintf('(count(preceding-sibling::%s) + 1)', $element),
                    'last()' => \sprintf('(count(preceding-sibling::%1$s) + count(following-sibling::%1$s) + 1)', $element),
                    default => $part,
                };
            }

            $result .= $part;
        }

        return $result;
    }

    public function __toString(): string
    {
        $path = $this->path.$this->element;
        $condition = '' === $this->condition ? '' : '['.$this->condition.']';

        return $path.$condition;
    }
}
