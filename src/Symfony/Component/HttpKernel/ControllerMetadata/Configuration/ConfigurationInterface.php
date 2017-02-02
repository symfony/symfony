<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\ControllerMetadata\Configuration;

/**
 * Shared interface between configurations on a controller action.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 *
 * @internal 
 */
interface ConfigurationInterface
{
    /**
     * True if this configuration option is allowed to be added multiple times
     * on the single controller action.
     *
     * @return bool
     */
    public function allowMultiple();

    /**
     * Returns the files that should be tracked for modification.
     *
     * @return string[]
     */
    public function getTrackedFiles();
}
