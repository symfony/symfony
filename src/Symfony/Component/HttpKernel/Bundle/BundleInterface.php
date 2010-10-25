<?php

namespace Symfony\Component\HttpKernel\Bundle;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
