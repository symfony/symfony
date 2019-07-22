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
 * Guesses if the property can be initialized through the constructor.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
interface PropertyInitializableExtractorInterface
{
    /**
     * Is the property initializable? Returns true if a constructor's parameter matches the given property name.
     */
    public function isInitializable(string $class, string $property, array $context = []): ?bool;
}
