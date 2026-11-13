<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * An @container condition: a size query on the nearest ancestor container, style() tests, or both joined by and,
 * or scroll-state() tests, optionally by name.
 *
 * The size condition reuses the media query syntax of the size features:
 * width, height, aspect-ratio and orientation, with the range syntax.
 *
 * @experimental
 *
 * @internal
 */
final class ContainerQuery
{
    /**
     * @param MediaQuery|null                    $condition A size condition, null for a style one only
     * @param list<array{bool, string, ?string}> $styles    The style() tests: whether negated, the property, the value (null for any); none for a size condition only
     * @param bool                               $any       Whether one style() or scroll-state() test is enough (or), else all are (and)
     * @param list<array{bool, string, string}>  $scroll    The scroll-state() tests: whether negated, the feature (stuck, snapped, scrollable, scrolled), the value
     */
    private function __construct(
        public readonly ?string $name,
        public readonly ?MediaQuery $condition,
        public readonly array $styles = [],
        public readonly bool $any = false,
        public readonly array $scroll = [],
    ) {
    }

    /**
     * Whether the scroll state of a scroll-state container passes the scroll-state() tests.
     *
     * @param array{stuck?: list<string>, snapped?: list<string>, scrollable?: list<string>, scrolled?: list<string>, offset?: array{array{0?: int, 1?: int}, array{0?: int, 1?: int}}, targetCurrent?: bool} $state The sides it is stuck to (top, bottom, left, right), the axes it is snapped on (x, y), the sides it can be scrolled to, and the sides it was last scrolled to
     */
    public function matchesScrollState(array $state): bool
    {
        foreach ($this->scroll as [$negated, $feature, $value]) {
            $actual = $state[$feature] ?? [];
            $wanted = match ($value) {
                'none' => [],
                'block-start' => ['top'],
                'block-end' => ['bottom'],
                'inline-start' => ['left'],
                'inline-end' => ['right'],
                'x', 'inline' => 'snapped' === $feature ? ['x'] : ['left', 'right'],
                'y', 'block' => 'snapped' === $feature ? ['y'] : ['top', 'bottom'],
                'both' => ['x', 'y'],
                default => [$value],
            };
            $passes = match (true) {
                [] === $wanted => [] === $actual,
                'both' === $value => [] === array_diff($wanted, $actual),
                default => [] !== array_intersect($wanted, $actual),
            };
            $passes = $negated ? !$passes : $passes;
            if ($passes === $this->any) {
                return $passes;
            }
        }

        return !$this->any;
    }

    /**
     * Whether the computed values of a style container pass the style() tests.
     *
     * @param array<string, string> $computed
     */
    public function matchesStyle(array $computed): bool
    {
        $normalize = static fn (string $value): string => strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
        foreach ($this->styles as [$negated, $property, $value]) {
            $actual = $computed[$property] ?? null;
            $passes = null === $value ? null !== $actual && 'initial' !== $actual : null !== $actual && $normalize($actual) === $normalize($value);
            $passes = $negated ? !$passes : $passes;
            if ($passes === $this->any) {
                return $passes;
            }
        }

        return !$this->any;
    }

    /**
     * @throws InvalidArgumentException when the prelude is not a container condition
     */
    public static function parse(string $prelude): self
    {
        $prelude = trim($prelude);
        $name = null;
        if (preg_match('/^(?!not\b|and\b|or\b|style\s*\(|scroll-state\s*\()(-?[a-z_][\w-]*)\s*(.*)$/is', $prelude, $m)) {
            $name = $m[1];
            $prelude = trim($m[2]);
        }
        // scroll-state() tests, like scroll-state(stuck: top) or scroll-state(snapped: x)
        if (preg_match('/^(?:not\s+)?scroll-state\s*\(/i', $prelude)) {
            return self::parseScrollState($name, $prelude);
        }
        // Size and style conditions joined by and: both must pass
        if (preg_match('/\bstyle\s*\(/i', $prelude)) {
            $size = $style = [];
            foreach (self::splitAnd($prelude) as $term) {
                if (preg_match('/^(?:not\s+)?style\s*\(/i', $term)) {
                    $style[] = $term;
                } else {
                    $size[] = $term;
                }
            }
            if ($size && $style) {
                if (array_any([...$size, ...$style], static fn (string $term) => \count(self::splitAnd($term, 'or')) > 1)) {
                    throw new InvalidArgumentException(\sprintf('Invalid container condition "%s": size and style conditions are only joined by and.', $prelude));
                }

                return new self($name, MediaQuery::parse(implode(' and ', $size)), self::parseStyle(null, implode(' and ', $style))->styles);
            }
        }
        // style() tests, like style(--theme: dark) and not style(--compact)
        if (preg_match('/^(?:not\s+)?style\s*\(/i', $prelude)) {
            return self::parseStyle($name, $prelude);
        }
        if (null !== $name && 'style' === strtolower($name) && str_starts_with($prelude, '(')) {
            return self::parseStyle(null, 'style'.$prelude);
        }
        if (preg_match('/\bstyle\s*\(/i', $prelude)) {
            throw new InvalidArgumentException(\sprintf('Invalid container condition "%s": size and style conditions cannot be mixed.', $prelude));
        }
        if ('' === $prelude || !str_starts_with(ltrim(preg_replace('/^not\s+/i', '', $prelude) ?? ''), '(')) {
            throw new InvalidArgumentException(\sprintf('Invalid container condition "%s".', $prelude));
        }

        return new self($name, MediaQuery::parse($prelude));
    }

    /**
     * Splits a condition on a top-level "and", or another keyword.
     *
     * @return list<string>
     */
    private static function splitAnd(string $condition, string $operator = 'and'): array
    {
        $parts = [];
        $depth = 0;
        $start = 0;
        $keyword = ' '.$operator.' ';
        for ($i = 0, $length = \strlen($condition); $i < $length; ++$i) {
            if ('(' === $condition[$i]) {
                ++$depth;
            } elseif (')' === $condition[$i]) {
                --$depth;
            } elseif (0 === $depth && ctype_space($condition[$i]) && 0 === strncasecmp(substr($condition, $i, \strlen($keyword)), $keyword, \strlen($keyword))) {
                $parts[] = trim(substr($condition, $start, $i - $start));
                $i += \strlen($keyword) - 1;
                $start = $i + 1;
            }
        }
        $parts[] = trim(substr($condition, $start));

        return $parts;
    }

    /**
     * @throws InvalidArgumentException when the condition is not scroll-state() tests joined by and or or
     */
    private static function parseScrollState(?string $name, string $condition): self
    {
        $values = [
            'stuck' => ['none', 'top', 'right', 'bottom', 'left', 'block-start', 'inline-start', 'block-end', 'inline-end'],
            'snapped' => ['none', 'x', 'y', 'block', 'inline', 'both'],
            'scrollable' => ['none', 'top', 'right', 'bottom', 'left', 'block-start', 'inline-start', 'block-end', 'inline-end', 'x', 'y', 'block', 'inline'],
            'scrolled' => ['none', 'top', 'right', 'bottom', 'left', 'block-start', 'inline-start', 'block-end', 'inline-end', 'x', 'y', 'block', 'inline'],
        ];
        $tests = [];
        $joins = [];
        $rest = trim($condition);
        while ('' !== $rest) {
            // scroll-state(stuck: top), or with the test in parentheses: scroll-state((stuck: top))
            if (!preg_match('/^(not\s+)?scroll-state\s*\(\s*(\()?\s*([a-z-]+)\s*:\s*([a-z-]+)\s*(?(2)\))\s*\)\s*(?:(and|or)\s+)?/is', $rest, $m)) {
                throw new InvalidArgumentException(\sprintf('Invalid container scroll-state condition "%s".', $condition));
            }
            $feature = strtolower($m[3]);
            $value = strtolower($m[4]);
            if (!\in_array($value, $values[$feature] ?? [], true)) {
                throw new InvalidArgumentException(\sprintf('Invalid container scroll-state test "%s: %s".', $feature, $value));
            }
            $tests[] = ['' !== $m[1], $feature, $value];
            if ('' !== ($m[5] ?? '')) {
                $joins[] = strtolower($m[5]);
            }
            $rest = substr($rest, \strlen($m[0]));
            if (\count($tests) > 32) {
                throw new InvalidArgumentException('Too many scroll-state() tests.');
            }
        }
        if (\count(array_unique($joins)) > 1 || \count($joins) !== \count($tests) - 1) {
            throw new InvalidArgumentException(\sprintf('Invalid container scroll-state condition "%s": and and or cannot be mixed without parentheses.', $condition));
        }

        return new self($name, null, [], ['or'] === array_unique($joins), $tests);
    }

    /**
     * @throws InvalidArgumentException when the condition is not style() tests joined by and or or
     */
    private static function parseStyle(?string $name, string $condition): self
    {
        $tests = [];
        $joins = [];
        $rest = trim($condition);
        while ('' !== $rest) {
            if (!preg_match('/^(not\s+)?style\s*\(\s*(--[\w-]+|[a-z-]+)\s*(?::\s*([^()]*?))?\s*\)\s*(?:(and|or)\s+)?/is', $rest, $m)) {
                throw new InvalidArgumentException(\sprintf('Invalid container style condition "%s"; size and style conditions cannot be mixed.', $condition));
            }
            $tests[] = ['' !== $m[1], str_starts_with($m[2], '--') ? $m[2] : strtolower($m[2]), isset($m[3]) && '' !== $m[3] ? $m[3] : null];
            if ('' !== ($m[4] ?? '')) {
                $joins[] = strtolower($m[4]);
            }
            $rest = substr($rest, \strlen($m[0]));
            if (\count($tests) > 32) {
                throw new InvalidArgumentException('Too many style() tests.');
            }
        }
        if (\count(array_unique($joins)) > 1 || \count($joins) !== \count($tests) - 1) {
            throw new InvalidArgumentException(\sprintf('Invalid container style condition "%s": and and or cannot be mixed without parentheses.', $condition));
        }

        return new self($name, null, $tests, ['or'] === array_unique($joins));
    }
}
