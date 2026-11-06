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
use Symfony\Component\HtmlSanitizer\TextSanitizer\StringSanitizer;

/**
 * Prevents links from giving the pages they open access to the current page.
 *
 * Browsers expose the current page as window.opener when a link uses the "opener"
 * link type or a named target. Named targets are turned into "_blank", which also
 * prevents links from navigating the frames of the page.
 *
 * @internal
 */
final class LinkTargetAttributeSanitizer implements AttributeSanitizerInterface
{
    public function getSupportedElements(): ?array
    {
        return ['a', 'area'];
    }

    public function getSupportedAttributes(): ?array
    {
        return ['rel', 'target'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if ('target' === $attribute) {
            return \in_array(StringSanitizer::htmlLower($value), ['', '_blank', '_self', '_parent', '_top'], true) ? $value : '_blank';
        }

        $linkTypes = preg_split('/[ \t\n\f\r]+/', $value, -1, \PREG_SPLIT_NO_EMPTY);
        $kept = array_filter($linkTypes, static fn (string $linkType) => 'opener' !== StringSanitizer::htmlLower($linkType));

        if (\count($kept) === \count($linkTypes)) {
            return $value;
        }

        return $kept ? implode(' ', $kept) : null;
    }
}
