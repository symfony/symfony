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
        return ['src', 'href', 'lowsrc', 'background', 'ping'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if ('a' === $element) {
            return UrlSanitizer::sanitize(
                $value,
                $config->getAllowedLinkSchemes(),
                $config->getForceHttpsUrls(),
                $config->getAllowedLinkHosts(),
                $config->getAllowRelativeLinks(),
            );
        }

        return UrlSanitizer::sanitize(
            $value,
            $config->getAllowedMediaSchemes(),
            $config->getForceHttpsUrls(),
            $config->getAllowedMediaHosts(),
            $config->getAllowRelativeMedias(),
        );
    }
}
