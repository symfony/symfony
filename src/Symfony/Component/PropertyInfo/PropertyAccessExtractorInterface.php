<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo;

/**
 * Guesses if the property can be accessed or mutated.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyAccessExtractorInterface
{
    /**
     * Is the property readable?
     */
    public function isReadable(string $class, string $property, array $context = []): ?bool;

    /**
     * Is the property writable?
     */
    public function isWritable(string $class, string $property, array $context = []): ?bool;
}
