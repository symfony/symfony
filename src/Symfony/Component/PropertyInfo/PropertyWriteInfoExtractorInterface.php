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
 * Extract write information for the property of a class.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface PropertyWriteInfoExtractorInterface
{
    /**
     * Get write information object for a given property of a class.
     */
    public function getWriteInfo(string $class, string $property, array $context = []): ?PropertyWriteInfo;
}
