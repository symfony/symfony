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

/** @internal */
final class SelectorRequirements
{
    private array $requirements = [];

    public static function names(\Dom\Document $document): array
    {
        $names = $classes = $ids = [];
        foreach ($document->getElementsByTagName('*') as $element) {
            $names[strtolower($element->localName)] = true;
            if ('' !== $class = trim($element->getAttribute('class') ?? '')) {
                foreach (preg_split('/\s+/', strtolower($class)) ?: [] as $name) {
                    $classes[$name] = true;
                }
            }
            if ('' !== $id = $element->getAttribute('id') ?? '') {
                $ids[strtolower($id)] = true;
            }
        }

        return [$names, $classes, $ids];
    }

    public static function required(string $selector): ?array
    {
        if (str_contains($selector, '\\')) {
            return null;
        }
        $items = [];
        foreach (Selector::splitTopLevel($selector) as $item) {
            $tags = $classes = $ids = [];
            $depth = 0;
            $start = true;
            for ($i = 0, $length = \strlen($item); $i < $length; ++$i) {
                $char = $item[$i];
                if ('(' === $char || '[' === $char) {
                    ++$depth;
                } elseif (')' === $char || ']' === $char) {
                    --$depth;
                } elseif ('"' === $char || "'" === $char) {
                    $end = strpos($item, $char, $i + 1);
                    if (false === $end) {
                        return null;
                    }
                    $i = $end;
                } elseif (0 === $depth && \in_array($char, [' ', '>', '+', '~'], true)) {
                    $start = true;
                    continue;
                } elseif (0 === $depth && ('.' === $char || '#' === $char) && preg_match('/\G[.#](-?[a-zA-Z_][\w-]*)/', $item, $m, 0, $i)) {
                    '.' === $char ? $classes[] = strtolower($m[1]) : $ids[] = strtolower($m[1]);
                    $i += \strlen($m[0]) - 1;
                } elseif (0 === $depth && $start) {
                    if (preg_match('/\G([a-zA-Z][\w-]*)(?![\w|-])/', $item, $m, 0, $i)) {
                        $tags[] = [strtolower($m[1]) => true];
                    } elseif (preg_match('/\G\*?:is\(\s*([a-zA-Z][\w-]*(?:\s*,\s*[a-zA-Z][\w-]*)*)\s*\)/', $item, $m, 0, $i)) {
                        $tags[] = array_fill_keys(array_map(strtolower(...), preg_split('/\s*,\s*/', $m[1]) ?: []), true);
                    }
                }
                $start = false;
            }
            $items[] = [$tags, $classes, $ids];
        }

        return $items;
    }

    public function mayMatch(string $selector, array $names): bool
    {
        $items = \array_key_exists($selector, $this->requirements) ? $this->requirements[$selector] : $this->requirements[$selector] = self::required($selector);
        if (null === $items) {
            return true;
        }
        foreach ($items as [$tags, $classes, $ids]) {
            foreach ($tags as $anyOf) {
                if (!array_intersect_key($anyOf, $names[0])) {
                    continue 2;
                }
            }
            foreach ($classes as $class) {
                if (!isset($names[1][$class])) {
                    continue 2;
                }
            }
            foreach ($ids as $id) {
                if (!isset($names[2][$id])) {
                    continue 2;
                }
            }

            return true;
        }

        return false;
    }
}
