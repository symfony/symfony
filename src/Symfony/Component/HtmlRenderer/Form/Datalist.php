<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Form;

use Symfony\Component\HtmlRenderer\Html\TreeScope;

/** @internal */
final class Datalist
{
    public static function completion(\Dom\Element $input, string $value, ?array $suggestions = null): ?string
    {
        if ('input' !== $input->localName || !\in_array(strtolower($input->getAttribute('type') ?? 'text'), ['text', 'search', 'tel', 'url', 'email', 'number', 'file', 'color'], true)) {
            return null;
        }
        if (null !== $suggestions) {
            return self::suffix($value, $suggestions);
        }
        $list = TreeScope::getElementById($input, $input->getAttribute('list') ?? '');
        if (null === $list || 'datalist' !== $list->localName) {
            return null;
        }
        $suggestions = [];
        foreach (SelectOptions::options($list) as $option) {
            $suggestions[] = SelectOptions::value($option);
        }

        return self::suffix($value, $suggestions);
    }

    public static function suffix(string $value, array $suggestions): ?string
    {
        if ('' === $value) {
            return null;
        }
        $length = mb_strlen($value);
        foreach ($suggestions as $suggestion) {
            if (mb_strlen($suggestion) > $length && 0 === mb_stripos($suggestion, $value)) {
                return mb_substr($suggestion, $length);
            }
        }

        return null;
    }
}
