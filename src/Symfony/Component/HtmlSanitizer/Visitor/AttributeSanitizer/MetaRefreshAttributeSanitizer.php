<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\TextSanitizer\UrlSanitizer;

/**
 * Sanitizes the URL embedded in the content attribute of a <meta http-equiv="refresh">
 * element, since the http-equiv value is not visible from a per-attribute sanitizer.
 *
 * The value is parsed following the "shared declarative refresh steps" of the HTML
 * specification: the time can be fractional, the separator can be a semicolon, a comma
 * or ASCII whitespace, and the "url" keyword is optional.
 *
 * The content attribute carries an unrelated value for other meta types (description,
 * keywords, generator…), which is passed through unchanged as long as it cannot be read
 * as a refresh directive by a browser.
 */
final class MetaRefreshAttributeSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['meta'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['content'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        // A leading time is the only marker of a refresh directive: without one, the value
        // cannot trigger a navigation and belongs to another meta type. Whitespace means
        // the ASCII whitespace of the specification, which \s does not match exactly.
        if (!preg_match('/^([ \t\n\f\r]*(?:\d[\d.]*|\.[\d.]*))(.*)$/s', $value, $m)) {
            return $value;
        }

        [, $time, $rest] = $m;

        // Nothing follows the time: the directive reloads the current document.
        if ('' === $rest) {
            return $value;
        }

        // The time is followed by a semicolon, a comma or ASCII whitespace, the latter
        // optionally followed by a semicolon or a comma. Anything else is dropped instead
        // of being trusted, so that a spelling this parser does not know about cannot slip
        // an unsanitized URL through.
        if (!preg_match('/^((?:[;,]|[ \t\n\f\r]+[;,]?)[ \t\n\f\r]*)(.*)$/s', $rest, $m)) {
            return null;
        }

        [, $separator, $url] = $m;

        $keyword = '';
        $quote = '';
        $trailing = '';

        // An incomplete "url=" keyword is part of the URL and disables the quote handling.
        $stripQuotes = 'u' !== strtolower($url[0] ?? '');

        if (preg_match('/^(url[ \t\n\f\r]*=[ \t\n\f\r]*)(.*)$/is', $url, $m)) {
            [, $keyword, $url] = $m;
            $stripQuotes = true;
        }

        if ($stripQuotes && ('"' === ($first = $url[0] ?? '') || "'" === $first)) {
            $quote = $first;
            $url = substr($url, 1);

            // Everything after the closing quote is ignored by browsers.
            if (false !== $end = strpos($url, $quote)) {
                $url = substr($url, 0, $end);
            }
        } else {
            $trimmed = rtrim($url, " \t\n\f\r");
            $trailing = substr($url, \strlen($trimmed));
            $url = $trimmed;
        }

        // An empty URL resolves to the document's own URL: the directive reloads the
        // current document, exactly like a directive made of a time only.
        if ('' === $url) {
            return $value;
        }

        $sanitized = UrlSanitizer::sanitize(
            $url,
            $config->getAllowedLinkSchemes(),
            $config->getForceHttpsUrls(),
            $config->getAllowedLinkHosts(),
            $config->getAllowRelativeLinks(),
        );

        if (null === $sanitized) {
            return null;
        }

        return $time.$separator.$keyword.$quote.$sanitized.$quote.$trailing;
    }
}
