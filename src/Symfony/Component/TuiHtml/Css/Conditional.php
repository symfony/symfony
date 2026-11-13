<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Css;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Resolves the CSS if() function: the value of the first branch whose condition passes.
 *
 * A condition is else, or media(), supports() and style() tests joined by not,
 * and and or; an unknown test is false.
 *
 * @internal
 */
final class Conditional
{
    private const TESTS = ['media', 'supports', 'style'];

    /**
     * Replaces each if() of a value by the value of its first branch whose condition passes.
     *
     * @param \Closure(string, string): bool $test Whether a test, media, supports or style, passes with its argument
     *
     * @throws InvalidArgumentException when no branch of an if() passes, which makes the declaration invalid at computed-value time
     */
    public static function resolve(string $value, \Closure $test): string
    {
        if (!str_contains($value, 'if(')) {
            return $value;
        }
        $offset = 0;
        // Not in a string, like content: "if(x)"
        while (null !== $start = Value::findOutsideStrings($value, '/(?<![\w-])if\(/i', $offset)) {
            $end = Value::closingParenthesis($value, $start + 2);
            $replacement = null;
            foreach (Selector::splitTopLevel(substr($value, $start + 3, $end - $start - 3), ';') as $branch) {
                if (null === $colon = self::topLevelColon($branch)) {
                    throw new InvalidArgumentException(\sprintf('Invalid if() branch "%s".', $branch));
                }
                if (self::passes(trim(substr($branch, 0, $colon)), $test)) {
                    $replacement = trim(substr($branch, $colon + 1));
                    break;
                }
            }
            if (null === $replacement) {
                throw new InvalidArgumentException(\sprintf('No branch of "%s" passes.', substr($value, $start, $end - $start + 1)));
            }
            $value = substr($value, 0, $start).$replacement.substr($value, $end + 1);
            // The value of the branch can have an if() of its own
            $offset = $start;
        }

        return $value;
    }

    /**
     * @param \Closure(string, string): bool $test
     */
    private static function passes(string $condition, \Closure $test): bool
    {
        $condition = trim($condition);
        if ('else' === strtolower($condition)) {
            return true;
        }
        if (preg_match('/^not\s+(.+)$/is', $condition, $m)) {
            return !self::passes($m[1], $test);
        }
        foreach (['and', 'or'] as $operator) {
            $operands = CssParser::splitOperator($condition, $operator);
            if (\count($operands) > 1) {
                foreach ($operands as $operand) {
                    if (self::passes($operand, $test) === ('or' === $operator)) {
                        return 'or' === $operator;
                    }
                }

                return 'and' === $operator;
            }
        }
        if (str_starts_with($condition, '(') && Value::closingParenthesis($condition, 0) === \strlen($condition) - 1) {
            return self::passes(substr($condition, 1, -1), $test);
        }
        if (preg_match('/^([a-z]+)\(/i', $condition, $m) && \in_array($name = strtolower($m[1]), self::TESTS, true) && Value::closingParenthesis($condition, \strlen($m[1])) === \strlen($condition) - 1) {
            return $test($name, trim(substr($condition, \strlen($m[1]) + 1, -1)));
        }

        return false;
    }

    private static function topLevelColon(string $branch): ?int
    {
        $depth = 0;
        $length = \strlen($branch);
        for ($i = 0; $i < $length; ++$i) {
            $char = $branch[$i];
            if ('(' === $char) {
                ++$depth;
            } elseif (')' === $char) {
                --$depth;
            } elseif (':' === $char && 0 === $depth) {
                return $i;
            }
        }

        return null;
    }
}
