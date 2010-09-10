<?php

namespace Symfony\Bundle\FrameworkBundle;

use Symfony\Framework\Bundle\Bundle;
use Symfony\Component\Form\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Bundle.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class FrameworkBundle extends Bundle
{
    /**
     * Boots the Bundle.
     */
    public function boot()
    {
        if ($this->container->hasParameter('csrf_secret')) {
            Form::setDefaultCsrfSecret($this->container->getParameter('csrf_secret'));
            Form::enableDefaultCsrfProtection();
        }
    }
}
