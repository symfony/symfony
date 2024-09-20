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

/**
 * Implements attribute-specific sanitization logic.
 *
 * @author Titouan Galopin <galopintitouan@gmail.com>
 */
interface AttributeSanitizerInterface
{
    /**
     * Returns the list of element names supported, or null to support all elements.
     *
     * @return list<string>|null
     */
    public function getSupportedElements(): ?array;

    /**
     * Returns the list of attributes names supported, or null to support all attributes.
     *
     * @return list<string>|null
     */
    public function getSupportedAttributes(): ?array;

    /**
     * Returns the sanitized value of a given attribute for the given element.
     */
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string;
}
