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
 * Extracts the list of properties available for the given class.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyListExtractorInterface
{
    /**
     * Gets the list of properties available for the given class.
     *
     * @param string $class
     *
     * @return string[]|null
     */
    public function getProperties($class, array $context = []);
}
