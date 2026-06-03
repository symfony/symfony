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
 * The content attribute carries an unrelated value for other meta types (description,
 * keywords, generator…), which is passed through unchanged.
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
        if (!preg_match('/^(\s*\d+\s*[;,]\s*url\s*=\s*)(["\']?)(.+?)\2(\s*)$/i', $value, $m)) {
            return $value;
        }

        $sanitized = UrlSanitizer::sanitize(
            $m[3],
            $config->getAllowedLinkSchemes(),
            $config->getForceHttpsUrls(),
            $config->getAllowedLinkHosts(),
            $config->getAllowRelativeLinks(),
        );

        if (null === $sanitized) {
            return null;
        }

        return $m[1].$m[2].$sanitized.$m[2].$m[4];
    }
}
