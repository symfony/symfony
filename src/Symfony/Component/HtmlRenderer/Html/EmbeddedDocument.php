<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Html;

/** @internal */
final class EmbeddedDocument
{
    /**
     * Resolves an iframe document without opening files or network connections.
     *
     * @param \Closure(string): ?string $load The host's bounded resource loader
     */
    public static function read(\Dom\Element $element, \Closure $load): ?string
    {
        if ('iframe' !== strtolower($element->localName)) {
            return null;
        }
        if ($element->hasAttribute('srcdoc')) {
            return (string) $element->getAttribute('srcdoc');
        }
        $source = trim($element->getAttribute('src') ?? '');
        if ('' === $source || 'about:blank' === strtolower($source)) {
            return null;
        }
        $html = $load($source);

        return null !== $html && mb_check_encoding($html, 'UTF-8') ? $html : null;
    }
}
