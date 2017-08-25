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
 * Gets info about PHP class properties.
 *
 * A convenient interface inheriting all specific info interfaces.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyInfoExtractorInterface extends PropertyTypeExtractorInterface, PropertyDescriptionExtractorInterface, PropertyAccessExtractorInterface, PropertyListExtractorInterface
{
}
