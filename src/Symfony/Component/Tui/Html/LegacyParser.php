<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Html;

/** @internal */
final class LegacyParser
{
    /** @return array{string, ?string} */
    public static function prepare(string $html): array
    {
        static $richOptions;
        $richOptions ??= null !== \Dom\HTMLDocument::createFromString('<!doctype html><select><option><span>x</span></option></select>', \LIBXML_NOERROR)->querySelector('option span');
        if ($richOptions || !str_contains(strtolower($html), '<select')) {
            return [$html, null];
        }
        // Older HTML parsers discard elements inside options. Parse selects as ordinary containers, then restore them.
        $tag = 'tui-select-'.bin2hex(random_bytes(8));
        $prepared = preg_replace_callback('~<!--.*?(?:-->|$)|<(script|style|textarea|title|xmp|iframe|noembed|noframes|plaintext)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>.*?(?:</\1\s*>|$)|</?[a-z][a-z0-9:-]*(?=[\s>/])(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~is', static fn (array $match) => preg_replace('~^(</?)select(?=[\s>/])~i', '$1'.$tag, $match[0]) ?? $match[0], $html);

        return [$prepared ?? $html, $tag];
    }

    public static function restore(\Dom\HTMLDocument $document, ?string $tag): void
    {
        if (null === $tag) {
            return;
        }
        foreach (iterator_to_array($document->getElementsByTagName($tag), false) as $source) {
            $select = $document->createElement('select');
            foreach ($source->attributes as $attribute) {
                $select->setAttribute($attribute->name, $attribute->value);
            }
            while (null !== $source->firstChild) {
                $select->appendChild($source->firstChild);
            }
            $source->replaceWith($select);
        }
    }
}
