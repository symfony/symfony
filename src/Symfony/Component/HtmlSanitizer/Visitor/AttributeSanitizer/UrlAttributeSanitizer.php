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
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
final class UrlAttributeSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        // Check all elements for URL attributes
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['src', 'srcset', 'imagesrcset', 'href', 'lowsrc', 'background', 'ping', 'action', 'formaction', 'poster', 'cite', 'data', 'codebase', 'archive', 'longdesc'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (\in_array($element, ['a', 'area'], true) || \in_array($attribute, ['action', 'formaction', 'cite'], true)) {
            $allowedSchemes = $config->getAllowedLinkSchemes();
            $allowedHosts = $config->getAllowedLinkHosts();
            $allowRelative = $config->getAllowRelativeLinks();
        } else {
            $allowedSchemes = $config->getAllowedMediaSchemes();
            $allowedHosts = $config->getAllowedMediaHosts();
            $allowRelative = $config->getAllowRelativeMedias();
        }

        if (\in_array($attribute, ['srcset', 'imagesrcset'], true)) {
            return self::sanitizeSrcset($value, $allowedSchemes, $config->getForceHttpsUrls(), $allowedHosts, $allowRelative);
        }

        return UrlSanitizer::sanitize($value, $allowedSchemes, $config->getForceHttpsUrls(), $allowedHosts, $allowRelative);
    }

    /**
     * Sanitizes an image candidate strings list: a comma-separated list of candidates,
     * each made of an URL optionally followed by width or pixel density descriptors.
     *
     * Candidates whose URL doesn't pass the policy are dropped; the attribute itself is
     * dropped when no candidate is left, as an empty list would be invalid anyway.
     *
     * @see https://html.spec.whatwg.org/multipage/images.html#parsing-a-srcset-attribute
     */
    private static function sanitizeSrcset(string $value, ?array $allowedSchemes, bool $forceHttps, ?array $allowedHosts, bool $allowRelative): ?string
    {
        $candidates = [];
        $length = \strlen($value);
        $position = 0;

        while ($position < $length) {
            $position += strspn($value, " \t\n\f\r,", $position);

            if ($position >= $length) {
                break;
            }

            $urlLength = strcspn($value, " \t\n\f\r", $position);
            $url = substr($value, $position, $urlLength);
            $position += $urlLength;

            // An URL may contain commas, so only trailing ones separate candidates
            if (str_ends_with($url, ',')) {
                $url = rtrim($url, ',');
                $descriptors = '';
            } else {
                $descriptors = self::consumeSrcsetDescriptors($value, $position, $length);
            }

            if (null === $url = UrlSanitizer::sanitize($url, $allowedSchemes, $forceHttps, $allowedHosts, $allowRelative)) {
                continue;
            }

            $candidates[] = '' === $descriptors ? $url : $url.' '.$descriptors;
        }

        return $candidates ? implode(', ', $candidates) : null;
    }

    /**
     * Consumes the descriptors of the current candidate, up to the next separating comma,
     * ignoring commas nested in parentheses as the HTML specification requires.
     */
    private static function consumeSrcsetDescriptors(string $value, int &$position, int $length): string
    {
        $start = $position;
        $inParens = false;

        while ($position < $length) {
            $char = $value[$position];

            if ($inParens) {
                $inParens = ')' !== $char;
            } elseif ('(' === $char) {
                $inParens = true;
            } elseif (',' === $char) {
                break;
            }

            ++$position;
        }

        $consumed = strtr(substr($value, $start, $position - $start), "\t\n\f\r", '    ');
        ++$position;

        $descriptors = [];

        foreach (explode(' ', $consumed) as $descriptor) {
            if ('' !== $descriptor) {
                $descriptors[] = $descriptor;
            }
        }

        return implode(' ', $descriptors);
    }
}
