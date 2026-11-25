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
final class EnvironmentValues
{
    /** @param \Closure(string): ?string $lookup */
    public static function substitute(string $value, \Closure $lookup): string
    {
        $offset = 0;
        while (null !== $start = ValueTokenizer::findOutsideStrings($value, '/(?<![\w-])env\(/i', $offset)) {
            $end = ValueTokenizer::closingParenthesis($value, $start + 3);
            $arguments = Selector::splitTopLevel(substr($value, $start + 4, $end - $start - 4));
            $name = strtolower(trim($arguments[0] ?? ''));
            $replacement = $lookup($name) ?? (isset($arguments[1]) ? implode(', ', \array_slice($arguments, 1)) : null);
            if (null === $replacement) {
                throw new InvalidArgumentException(\sprintf('The environment variable "%s" is not defined.', $name));
            }
            $value = substr($value, 0, $start).$replacement.substr($value, $end + 1);
            $offset = $start + \strlen($replacement);
        }

        return $value;
    }
}
