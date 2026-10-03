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
 * Extracts how a property of a class can be read.
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
