<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * FrameworkBundle Controller gives you convenient access to all commonly needed services.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class BaseController implements ControllerInterface
{
    protected $container;

    /**
     * Sets the Container associated with this Controller.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }
}
