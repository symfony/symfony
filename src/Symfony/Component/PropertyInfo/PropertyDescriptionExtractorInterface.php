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
 * Guesses the property's human readable description.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyDescriptionExtractorInterface
{
    /**
     * Gets the short description of the property.
     *
     * @return string|null
     */
    public function getShortDescription(string $class, string $property, array $context = []);

    /**
     * Gets the long description of the property.
     *
     * @return string|null
     */
    public function getLongDescription(string $class, string $property, array $context = []);
}
