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
final class CustomProperties
{
    private const MAX_SUBSTITUTED_LENGTH = 65536;

    public static function substitute(string $value, array $properties, ?\Closure $environment = null): string
    {
        if (null !== $environment && str_contains($value, 'env(')) {
            $value = $environment($value);
        }
        if (false === stripos($value, 'var(')) {
            return $value;
        }
        $invalid = [];
        $properties = self::withoutCycles($properties, $invalid);
        $resolved = array_fill_keys(array_keys($invalid), new InvalidArgumentException('Custom properties reference each other in a cycle.'));

        return self::substituteIn($value, $properties, 0, $resolved, $environment);
    }

    public static function resolve(array $values): array
    {
        $properties = self::withoutCycles($values);
        $memo = [];
        $result = [];
        foreach ($properties as $property => $value) {
            try {
                $result[$property] = self::substituteIn($value, $properties, 0, $memo, null);
            } catch (InvalidArgumentException) {
            }
        }

        return $result;
    }

    public static function references(string $value, ?array $available = null, int $depth = 0): array
    {
        if ($depth >= 64) {
            throw new InvalidArgumentException('Custom property references are nested too deeply.');
        }
        $names = [];
        $offset = 0;
        while (null !== $start = ValueTokenizer::findOutsideStrings($value, '/(?<![\\w-])var\\(/i', $offset)) {
            $end = ValueTokenizer::closingParenthesis($value, $start + 3);
            $arguments = substr($value, $start + 4, $end - $start - 4);
            [$name, $fallback] = array_pad(explode(',', $arguments, 2), 2, null);
            $name = trim($name);
            if (null === $available || isset($available[$name])) {
                $names[$name] = true;
            } elseif (null !== $fallback) {
                $names += array_fill_keys(self::references($fallback, $available, $depth + 1), true);
            }
            $offset = null === $available ? $start + 4 : $end + 1;
        }

        return array_keys($names);
    }

    public static function dependents(array $inputs, array $available, array $affected): array
    {
        if ([] === $affected) {
            return [];
        }
        $dependents = [];
        foreach ($inputs as $property => $value) {
            try {
                foreach (self::references($value, $available) as $reference) {
                    $dependents[$reference][] = $property;
                }
            } catch (InvalidArgumentException) {
            }
        }
        $queue = array_keys($affected);
        for ($index = 0; $index < \count($queue); ++$index) {
            foreach ($dependents[$queue[$index]] ?? [] as $property) {
                if (!isset($affected[$property])) {
                    $affected[$property] = true;
                    $queue[] = $property;
                }
            }
        }

        return $affected;
    }

    /** @param-out array<string, true> $invalid */
    private static function withoutCycles(array $properties, ?array &$invalid = null): array
    {
        $graph = [];
        foreach ($properties as $property => $value) {
            if (!str_starts_with($property, '--')) {
                continue;
            }
            try {
                $graph[$property] = array_intersect_key(array_fill_keys(self::references($value), true), $properties);
            } catch (InvalidArgumentException) {
                $graph[$property] = [];
                unset($properties[$property]);
            }
        }
        $done = [];
        $path = [];
        $invalid = [];
        $visit = static function (string $name) use (&$visit, &$done, &$path, &$invalid, $graph): void {
            if (isset($path[$name])) {
                foreach (\array_slice(array_keys($path), $path[$name]) as $cyclic) {
                    $invalid[$cyclic] = true;
                }

                return;
            }
            if (isset($done[$name])) {
                return;
            }
            if (\count($path) > 32) {
                $invalid[$name] = true;

                return;
            }
            $path[$name] = \count($path);
            foreach ($graph[$name] ?? [] as $dependency => $_) {
                $visit($dependency);
            }
            unset($path[$name]);
            $done[$name] = true;
        };
        foreach ($graph as $name => $_) {
            $visit($name);
        }

        return array_diff_key($properties, $invalid);
    }

    private static function substituteIn(string $value, array $properties, int $depth, array &$resolved, ?\Closure $environment): string
    {
        if (null !== $environment && str_contains($value, 'env(')) {
            $value = $environment($value);
        }
        if (\strlen($value) > self::MAX_SUBSTITUTED_LENGTH) {
            throw new InvalidArgumentException('The custom property value is too long.');
        }
        if (!str_contains($value, 'var(')) {
            return $value;
        }
        if ($depth > 32) {
            throw new InvalidArgumentException('Custom properties reference each other in a cycle.');
        }
        $offset = 0;
        while (null !== $start = ValueTokenizer::findOutsideStrings($value, '/(?<![\w-])var\(/i', $offset)) {
            $end = ValueTokenizer::closingParenthesis($value, $start + 3);
            // The name, up to the first comma, and the fallback, all after it as written, commas too, like CSS: empty
            // in the var(--tw-ring-inset,) of the rings of Tailwind, a comma in var(--a,,)
            $arguments = substr($value, $start + 4, $end - $start - 4);
            $comma = strpos($arguments, ',');
            $name = trim(false === $comma ? $arguments : substr($arguments, 0, $comma));
            $replacement = $properties[$name] ?? null;
            // Set to initial, a custom property is the guaranteed-invalid value: the fallback, like CSS, like the
            // --bs-table-color-state: initial of the tables of Bootstrap
            if (null !== $replacement && 'initial' === strtolower(trim($replacement))) {
                $replacement = null;
            }
            $failure = ($resolved[$name] ?? null) instanceof InvalidArgumentException ? $resolved[$name] : null;
            if (null !== $replacement) {
                if (\array_key_exists($name, $resolved)) {
                    $replacement = null !== $failure ? null : $resolved[$name];
                } else {
                    try {
                        $replacement = $resolved[$name] = self::substituteIn($replacement, $properties, $depth + 1, $resolved, $environment);
                    } catch (InvalidArgumentException $exception) {
                        $resolved[$name] = $failure = $exception;
                        $replacement = null;
                    }
                }
            }
            if (null === $replacement) {
                if (false === $comma) {
                    throw $failure ?? new InvalidArgumentException(\sprintf('The custom property "%s" is not defined.', $name));
                }
                $replacement = self::substituteIn(trim(substr($arguments, $comma + 1)), $properties, $depth + 1, $resolved, $environment);
            }
            // Tokens, not text, like CSS: a value set against a word or a number stays apart from it, like the
            // var(--bs-border-width)var(--bs-border-style) of a minified Bootstrap, 1px solid and not 1pxsolid
            $before = substr($value, 0, $start);
            $after = substr($value, $end + 1);
            if ('' !== $trimmed = trim($replacement)) {
                if (preg_match('/[\w%)]$/', $before) && preg_match('/^[\w.#+-]/', $trimmed)) {
                    $replacement = ' '.$replacement;
                }
                if (preg_match('/^[\w.#(]/', $after) && preg_match('/[\w%)]$/', $trimmed)) {
                    $replacement .= ' ';
                }
            }
            // A property using another several times, which uses another several times, grows exponentially: past this,
            // the value is invalid, like the guaranteed-invalid value browsers give such a value
            if (\strlen($before) + \strlen($replacement) + \strlen($after) > self::MAX_SUBSTITUTED_LENGTH) {
                throw new InvalidArgumentException('The value is too long once its custom properties are substituted.');
            }
            $value = $before.$replacement.$after;
            $offset = $start + \strlen($replacement);
        }

        return $value;
    }
}
