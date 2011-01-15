<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

/**
 * BundleInterface.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface BundleInterface
{
    /**
     * Boots the Bundle.
     */
    function boot();

    /**
     * Shutdowns the Bundle.
     */
    function shutdown();
}
