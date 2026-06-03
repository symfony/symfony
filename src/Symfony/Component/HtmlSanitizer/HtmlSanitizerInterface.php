<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer;

/**
 * Sanitizes an untrusted HTML input for safe insertion into a document's DOM.
 *
 * This interface is inspired by the W3C Standard Draft about a HTML Sanitizer API
 * ({@see https://wicg.github.io/sanitizer-api/}).
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface HtmlSanitizerInterface
{
    /**
     * Sanitizes an untrusted HTML input for a <body> context.
     *
     * This method is NOT context sensitive: it assumes the returned HTML string
     * will be injected in a "body" context, and therefore will drop tags only
     * allowed in the "head" element. To sanitize a string for injection
     * in the "head" element, use {@see HtmlSanitizerInterface::sanitizeFor()}.
     */
    public function sanitize(string $input): string;

    /**
     * Sanitizes an untrusted HTML input for a given context.
     *
     * This method is context sensitive: by providing a parent element name
     * (body, head, title, ...), the sanitizer will adapt its rules to only
     * allow elements that are valid inside the given parent element.
     */
    public function sanitizeFor(string $element, string $input): string;
}
