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

use Symfony\Component\HtmlRenderer\Exception\InvalidArgumentException;

/** @internal */
final class ElementFunctions
{
    public static function siblings(string $value, ?int $index, ?int $count): string
    {
        return preg_replace_callback('/(?<![\w-])sibling-(index|count)\(\s*\)/i', static fn (array $m) => (string) (('index' === strtolower($m[1]) ? $index : $count) ?? throw new InvalidArgumentException('The element has no siblings.')), $value) ?? $value;
    }

    public static function attributes(string $value, array $attributes, ?\Closure $syntaxValidator = null): string
    {
        $offset = 0;
        $iterations = 0;
        while (null !== $start = ValueTokenizer::findOutsideStrings($value, '/(?<![\w-])attr\(/i', $offset)) {
            if (++$iterations > 64 || \strlen($value) > 65536) {
                throw new InvalidArgumentException('The attribute substitution exceeds its work limit.');
            }
            $end = ValueTokenizer::closingParenthesis($value, $start + 4);
            $arguments = Selector::splitTopLevel(substr($value, $start + 5, $end - $start - 5));
            $fallback = isset($arguments[1]) ? implode(', ', \array_slice($arguments, 1)) : null;
            if (!preg_match('/^\s*([a-z_][\w-]*)(?:\s+(.+?))?\s*$/is', $arguments[0] ?? '', $a)) {
                throw new InvalidArgumentException(\sprintf('Invalid attr() in "%s".', $value));
            }
            $type = strtolower(trim($a[2] ?? ''));
            $attribute = $attributes[strtolower($a[1])] ?? null;
            $replacement = null === $attribute ? null : match (true) {
                '' === $type, 'raw-string' === $type => '"'.addcslashes($attribute, '"\\').'"',
                'number' === $type => is_numeric(trim($attribute)) ? trim($attribute) : null,
                (bool) preg_match('/^type\(\s*(.+)\s*\)$/s', $type, $t) => !str_contains($attribute, 'var(') && !str_contains($attribute, 'attr(') && (null !== $syntaxValidator ? $syntaxValidator($t[1], $attribute) : PropertySyntax::matches($t[1], $attribute)) ? trim($attribute) : null,
                '%' === $type, (bool) preg_match('/^[a-z]+$/', $type) => is_numeric(trim($attribute)) ? trim($attribute).$type : null,
                default => throw new InvalidArgumentException(\sprintf('Invalid attr() type "%s".', $type)),
            };
            // A fallback can use attr() too, not the value of an attribute
            $offset = null !== $replacement ? $start + \strlen($replacement) : $start;
            $replacement ??= $fallback ?? throw new InvalidArgumentException(\sprintf('The "%s" attribute has no value of its type.', $a[1]));
            $value = substr($value, 0, $start).$replacement.substr($value, $end + 1);
        }

        return $value;
    }
}
