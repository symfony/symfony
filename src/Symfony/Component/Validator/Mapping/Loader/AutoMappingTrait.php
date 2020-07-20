<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Mapping\AutoMappingStrategy;
use Symfony\Component\Validator\Mapping\ClassMetadata;

/**
 * Utility methods to create auto mapping loaders.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
trait AutoMappingTrait
{
    private function isAutoMappingEnabledForClass(ClassMetadata $metadata, string $classValidatorRegexp = null): bool
    {
        // Check if AutoMapping constraint is set first
        if (AutoMappingStrategy::NONE !== $strategy = $metadata->getAutoMappingStrategy()) {
            return AutoMappingStrategy::ENABLED === $strategy;
        }

        // Fallback on the config
        return null !== $classValidatorRegexp && preg_match($classValidatorRegexp, $metadata->getClassName());
    }
}
