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
 * Description extractor Interface.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyDescriptionExtractorInterface
{
    /**
     * Gets the short description of the property.
     *
     * @param string $class
     * @param string $property
     * @param array  $context
     *
     * @return string|null
     */
    public function getShortDescription($class, $property, array $context = array());

    /**
     * Gets the long description of the property.
     *
     * @param string $class
     * @param string $property
     * @param array  $context
     *
     * @return string|null
     */
    public function getLongDescription($class, $property, array $context = array());
}
