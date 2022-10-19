<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation;

use Symfony\Component\Translation\Exception\InvalidArgumentException;

/**
 * Asserts (syntactical) validity for given locale identifiers.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class LocaleValidator
{
    /**
     * Asserts that the locale is valid, throws an Exception if not.
     *
     * @throws InvalidArgumentException If the locale contains invalid characters
     */
    public static function validate(string $locale): void
    {
        if (!preg_match('/^[a-z0-9@_\\.\\-]*$/i', $locale)) {
            throw new InvalidArgumentException(sprintf('Invalid "%s" locale.', $locale));
        }
    }
}
