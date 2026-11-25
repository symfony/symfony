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

final class DeclarationParser
{
    public static function parse(string $css, ?\Closure $report = null): array
    {
        $declarations = [];
        foreach (Selector::splitTopLevel(self::stripComments($css), ';') as $declaration) {
            if ('' === trim($declaration)) {
                continue;
            }
            $colon = strpos($declaration, ':');
            if (false === $colon) {
                $report && $report(\sprintf('Invalid CSS declaration "%s".', $declaration));
                continue;
            }
            $property = trim(substr($declaration, 0, $colon));
            $value = trim(substr($declaration, $colon + 1));
            $important = false;
            if (preg_match('/\s*!\s*important$/i', $value, $m)) {
                $important = true;
                $value = trim(substr($value, 0, -\strlen($m[0])));
            }
            if ('' === $property || '' === $value && !str_starts_with($property, '--')) {
                $report && $report(\sprintf('Invalid CSS declaration "%s".', $declaration));
                continue;
            }
            $property = str_starts_with($property, '--') ? $property : strtolower($property);
            $property = match ($property) {
                'grid-gap' => 'gap',
                'grid-row-gap' => 'row-gap',
                'grid-column-gap' => 'column-gap',
                default => $property,
            };
            $declarations[] = new Declaration($property, $value, $important);
        }

        return $declarations;
    }

    public static function stripComments(string $css): string
    {
        if (!str_contains($css, '/*')) {
            return $css;
        }
        // Keep strings intact: a "/*" inside quotes is not a comment. Scanned, not matched by a pattern: a style sheet of
        // a framework, like the 600 kB of Tailwind of Laravel, has quotes far apart that a pattern backtracks over
        $out = '';
        $length = \strlen($css);
        $at = 0;
        while ($at < $length) {
            $next = strcspn($css, '/"\'\\', $at) + $at;
            $out .= substr($css, $at, $next - $at);
            if ($next >= $length) {
                break;
            }
            $char = $css[$next];
            // An escape, like the \' of the .before\:content-\[\'—\'\] of Tailwind: the character it escapes is no quote
            if ('\\' === $char) {
                $out .= substr($css, $next, 2);
                $at = $next + 2;
                continue;
            }
            if ('/' === $char) {
                if ('*' !== ($css[$next + 1] ?? '')) {
                    $out .= '/';
                    $at = $next + 1;
                    continue;
                }
                // An unclosed comment runs to the end
                $end = strpos($css, '*/', $next + 2);
                $at = false === $end ? $length : $end + 2;
                continue;
            }
            // A string, its escapes included, to its closing quote or the end
            $end = $next + 1;
            while ($end < $length && $char !== $css[$end]) {
                $end += '\\' === $css[$end] ? 2 : 1;
            }
            $out .= substr($css, $next, min($length, $end + 1) - $next);
            $at = $end + 1;
        }

        return $out;
    }
}
