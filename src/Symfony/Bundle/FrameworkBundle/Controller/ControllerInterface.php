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
 * FrameworkBundle ControllerInterface is a simple interface for controllers.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface ControllerInterface
{
    /**
     * Sets the Container associated with this Controller.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     */
    function setContainer(ContainerInterface $container);
}
