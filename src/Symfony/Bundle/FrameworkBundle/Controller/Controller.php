<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Controller is a simple implementation of a Controller.
 *
 * It provides methods to common features needed in controllers.
 *
 * @deprecated since Symfony 4.2, use "Symfony\Bundle\FrameworkBundle\Controller\AbstractController" instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Controller implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ControllerTrait;

    /**
     * Gets a container configuration parameter by its name.
     *
     * @return array|bool|float|int|string|\UnitEnum|null
     *
     * @final
     */
    protected function getParameter(string $name)
    {
        return $this->container->getParameter($name);
    }
}
