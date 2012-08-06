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
 * ConfigurableRequirementsInterface must be implemented by URL generators that
 * can be configured whether an exception should be generated when the parameters
 * do not match the requirements. It is also possible to disable the requirements
 * check for URL generation completely to improve performance when you know that
 * the parameters meet the requirements anyway (because they are from a trusted
 * source or you validated them beforehand which should be the case in production
 * environment as it would otherwise break your site).
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
interface ConfigurableRequirementsInterface
{
    /**
     * Enables or disables the exception on incorrect parameters.
     * Passing null will deactivate the requirements check completely.
     *
     * @param Boolean|null $enabled
     */
    public function setStrictRequirements($enabled);

    /**
     * Returns whether to throw an exception on incorrect parameters.
     * Null means the requirements check is deactivated completely.
     *
     * @return Boolean|null
     */
    public function isStrictRequirements();
}
