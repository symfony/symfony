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

trait VersionAwareTest
{
    protected static $supportedFeatureSetVersion = 304;

    /**
     * @param int $requiredFeatureSetVersion
     */
    protected function requiresFeatureSet($requiredFeatureSetVersion)
    {
        if ($requiredFeatureSetVersion > static::$supportedFeatureSetVersion) {
            $this->markTestSkipped(sprintf('Test requires features from symfony/form %.2f but only version %.2f is supported.', $requiredFeatureSetVersion / 100, static::$supportedFeatureSetVersion / 100));
        }
    }
}
