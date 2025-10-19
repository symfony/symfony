<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Attribute;

/**
 * Marks a property or nested object to be sanitized during denormalization.
 *
 * When applied to a string property, the property value will be sanitized using the specified sanitizer.
 * If no sanitizer is specified, the default sanitizer will be used.
 *
 * @author Mohamed Senoussi <lesfootix@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Sanitize
{
    /**
     * @param string|null $sanitizer The name of the sanitizer to use (null uses the default sanitizer)
     */
    public function __construct(
        public ?string $sanitizer = null,
    ) {}
}

