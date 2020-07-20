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
 * Extract read information for the property of a class.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface PropertyReadInfoExtractorInterface
{
    /**
     * Get read information object for a given property of a class.
     */
    public function getReadInfo(string $class, string $property, array $context = []): ?PropertyReadInfo;
}
