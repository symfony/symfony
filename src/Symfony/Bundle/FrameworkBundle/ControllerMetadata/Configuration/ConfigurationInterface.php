<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\ControllerMetadata\Configuration;

/**
 * ConfigurationInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ConfigurationInterface
{
    /**
     * Returns the alias name for an annotated configuration.
     *
     * @return string
     */
    public function getAliasName();

    /**
     * Returns whether multiple annotations of this type are allowed.
     *
     * @return bool
     */
    public function allowArray();
}
