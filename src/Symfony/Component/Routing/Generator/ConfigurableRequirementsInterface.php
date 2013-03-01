<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

/**
 * ConfigurableRequirementsInterface must be implemented by URL generators in order
 * to be able to configure whether an exception should be generated when the
 * parameters do not match the requirements.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ConfigurableRequirementsInterface
{
    /**
     * Enables or disables the exception on incorrect parameters.
     *
     * @param Boolean $enabled
     */
    public function setStrictRequirements($enabled);

    /**
     * Gets the strict check of incorrect parameters.
     *
     * @return Boolean
     */
    public function isStrictRequirements();
}
