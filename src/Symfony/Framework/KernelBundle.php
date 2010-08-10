<?php

namespace Symfony\Framework;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Framework\ClassCollectionLoader;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * KernelBundle.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class KernelBundle extends Bundle
{
    /**
     * Boots the Bundle.
     */
    public function boot()
    {
        if ($this->container->has('error_handler')) {
            $this->container['error_handler'];
        }
    }
}
