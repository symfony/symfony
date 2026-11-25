<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Css;

use Symfony\Component\HtmlRenderer\Compatibility\Support;
use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/**
 * Parses media conditions independently of viewport and device capabilities.
 *
 * @internal
 */
class MediaQuery
{
    public static function parse(string $query, array $customMedia = []): static
    {
        return self::parseList($query, $customMedia, []);
    }

    /** @param \Closure(string, string, ?string): ?string $map */
    public function mapValues(\Closure $map): static
    {
        $queries = [];
        foreach ($this->queries as $conditions) {
            $mapped = false;
            if (false !== $conditions) {
                $mapped = [];
                foreach ($conditions as [$name, $operator, $value]) {
                    $mapped[] = [$name, $operator, $value instanceof self ? $value->mapValues($map) : $map($name, $operator, $value)];
                }
            }
            $queries[] = $mapped;
        }

        return new static($this->source, $queries, $this->negated);
    }

    public function usesBlockAxis(): bool
    {
        foreach ($this->queries as $conditions) {
            foreach ($conditions ?: [] as [$feature, , $value]) {
                if (\in_array($feature, ['height', 'block-size', 'aspect-ratio', 'orientation'], true) || $value instanceof self && $value->usesBlockAxis()) {
                    return true;
                }
            }
        }

        return false;
    }

    public function evaluate(\Closure $feature): bool
    {
        foreach ($this->queries as $i => $conditions) {
            $matches = false !== $conditions;
            foreach ($conditions ?: [] as [$name, $operator, $value]) {
                if (!($value instanceof self ? $value->evaluate($feature) : $feature($name, $operator, $value))) {
                    $matches = false;
                    break;
                }
            }
            if ($matches !== $this->negated[$i]) {
                return true;
            }
        }

        return false;
    }

    protected function certainty(\Closure $feature): ?bool
    {
        $unknown = false;
        foreach ($this->queries as $i => $conditions) {
            $matches = false !== $conditions;
            foreach ($conditions ?: [] as [$name, $operator, $value]) {
                $condition = $value instanceof self ? $value->certainty($feature) : $feature($name, $operator, $value);
                if (false === $condition) {
                    $matches = false;
                    break;
                }
                if (null === $condition) {
                    $matches = null;
                }
            }
            if (null === $matches) {
                $unknown = true;
            } elseif ($matches !== $this->negated[$i]) {
                return true;
            }
        }

        return $unknown ? null : false;
    }

    public static function getSupport(): array
    {
        return ['css.at-rules.media' => Support::supported('Feature evaluation is provided by the rendering context.'), 'css.at-rules.media.range-syntax' => Support::supported()];
    }

    public static function resolution(string $value): ?float
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)(dppx|x|dpi|dpcm)$/', strtolower(trim($value)), $m)) {
            return null;
        }

        return match ($m[2]) {
            'dppx', 'x' => (float) $m[1],
            'dpi' => (float) $m[1] / 96,
            'dpcm' => (float) $m[1] * 2.54 / 96,
        };
    }

    public static function compare(int|float $actual, string $operator, int|float|null $expected): bool
    {
        if ('bool' === $operator) {
            return 0 != $actual;
        }
        if (null === $expected) {
            return false;
        }

        return match ($operator) {
            '=' => abs($actual - $expected) < 0.001,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected + 0.001,
            '>' => $actual > $expected,
            '>=' => $actual >= $expected - 0.001,
            default => false,
        };
    }

    public static function ratio(string $value): ?float
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*(?:\/\s*(\d+(?:\.\d+)?))?$/', $value, $m)) {
            return null;
        }

        $denominator = (float) ($m[2] ?? 1);

        return 0.0 === $denominator ? null : (float) $m[1] / $denominator;
    }

    final protected function __construct(public readonly string $source, protected readonly array $queries, protected readonly array $negated)
    {
    }

    private static function parseList(string $query, array $customMedia, array $resolving, int $depth = 0): static
    {
        if ($depth > 64 || \strlen($query) > 65536) {
            return new static($query, [false], [false]);
        }
        if ('' === trim($query)) {
            return new static($query, [], []);
        }
        $alternatives = Selector::splitTopLevel($query);
        if (\count($alternatives) > 1) {
            return new static($query, array_map(static fn (string $part) => [['logical', 'custom', self::parseList($part, $customMedia, $resolving, $depth + 1)]], $alternatives), array_fill(0, \count($alternatives), false));
        }
        $single = strtolower(trim($query));
        if (str_starts_with($single, 'only ')) {
            $single = substr($single, 5);
        }
        if (str_starts_with($single, 'not ')) {
            return new static($query, [[['logical', 'custom', self::parseList(substr($single, 4), $customMedia, $resolving, $depth + 1)]]], [true]);
        }
        foreach (['or', 'and'] as $operator) {
            $operands = CssParser::splitOperator($single, $operator);
            if (\count($operands) > 1) {
                $nested = array_map(static fn (string $part) => ['logical', 'custom', self::parseList($part, $customMedia, $resolving, $depth + 1)], $operands);

                return new static($query, 'or' === $operator ? array_map(static fn (array $part) => [$part], $nested) : [$nested], array_fill(0, 'or' === $operator ? \count($nested) : 1, false));
            }
        }
        if ('' === $single || preg_match('/^[a-z][a-z-]*$/', $single)) {
            return new static($query, [[['media-type', '=', '' === $single ? 'all' : $single]]], [false]);
        }
        $conditions = false;
        if (str_starts_with($single, '(') && str_ends_with($single, ')') && ValueTokenizer::closingParenthesis($single, 0) === \strlen($single) - 1) {
            $inner = trim(substr($single, 1, -1));
            if (str_starts_with($inner, '(') || str_starts_with($inner, 'not ')) {
                $conditions = [['logical', 'custom', self::parseList($inner, $customMedia, $resolving, $depth + 1)]];
            } else {
                $conditions = preg_match('/^(--[\w-]+)$/', $inner, $m) ? self::customCondition($m[1], $customMedia, $resolving) : self::parseConditions($inner);
            }
        }

        return new static($query, [$conditions ?? false], [false]);
    }

    private static function customCondition(string $name, array $customMedia, array $resolving): ?array
    {
        $query = $customMedia[$name] ?? null;
        if (null === $query || \count($resolving) > 64 || \in_array($name, $resolving, true)) {
            return null;
        }
        $query = match (strtolower(trim($query))) {
            'true' => 'all',
            'false' => 'not all',
            default => $query,
        };

        return [[$name, 'custom', self::parseList($query, $customMedia, [...$resolving, $name])]];
    }

    private static function parseConditions(string $condition): ?array
    {
        // Double range syntax: (40 < width <= 120)
        if (preg_match('/^(.+?)\s*(<=|<|>=|>)\s*([a-z-]+)\s*(<=|<|>=|>)\s*(.+)$/', trim($condition), $m)) {
            $first = self::parseCondition($m[1].$m[2].$m[3]);
            $second = self::parseCondition($m[3].$m[4].$m[5]);

            return null === $first || null === $second ? null : [$first, $second];
        }
        $single = self::parseCondition($condition);

        return null === $single ? null : [$single];
    }

    private static function parseCondition(string $condition): ?array
    {
        $condition = trim($condition);
        // Range syntax: (width >= 80), (80 <= width)
        if (preg_match('/^([a-z-]+)\s*(<=|>=|<|>|=)\s*(.+)$/', $condition, $m)) {
            return [$m[1], $m[2], trim($m[3])];
        }
        if (preg_match('/^(.+?)\s*(<=|>=|<|>|=)\s*([a-z-]+)$/', $condition, $m)) {
            $flipped = ['<' => '>', '<=' => '>=', '>' => '<', '>=' => '<=', '=' => '='];

            return [$m[3], $flipped[$m[2]], trim($m[1])];
        }
        if (preg_match('/^(min-|max-)?([a-z-]+)\s*(?::\s*(.+))?$/', $condition, $m)) {
            $feature = $m[2];
            $value = isset($m[3]) ? trim($m[3]) : null;
            if ('' !== $m[1] && null === $value) {
                throw new InvalidArgumentException(\sprintf('The "%s%s" media feature needs a value.', $m[1], $feature));
            }

            return [$feature, match ($m[1]) {
                'min-' => '>=',
                'max-' => '<=',
                default => null === $value ? 'bool' : '=',
            }, $value];
        }

        return null;
    }
}
