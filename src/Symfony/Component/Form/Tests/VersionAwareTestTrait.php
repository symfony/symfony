<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

/**
 * @deprecated since Symfony 7.2, use feature detection instead.
 */
trait VersionAwareTestTrait
{
    protected static int $supportedFeatureSetVersion = 404;

    /**
     * @deprecated since Symfony 7.2, use feature detection instead.
     */
    protected function requiresFeatureSet(int $requiredFeatureSetVersion)
    {
        trigger_deprecation('symfony/form', '7.2', 'The "%s" trait is deprecated, use feature detection instead.', VersionAwareTestTrait::class);

        if ($requiredFeatureSetVersion > static::$supportedFeatureSetVersion) {
            $this->markTestSkipped(\sprintf('Test requires features from symfony/form %.2f but only version %.2f is supported.', $requiredFeatureSetVersion / 100, static::$supportedFeatureSetVersion / 100));
        }
    }
}
