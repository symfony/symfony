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
 * @deprecated since Symfony 4.2, use {@see AbstractController} instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Controller implements ContainerAwareInterface
{
    use ContainerAwareTrait;
    use ControllerTrait;

    public function __construct()
    {
+        @trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.2, use %s instead.', self::class, AbstractController::class), E_USER_DEPRECATED);
    }

    /**
     * Gets a container configuration parameter by its name.
     *
     * @return mixed
     *
     * @final
     */
    protected function getParameter(string $name)
    {
        return $this->container->getParameter($name);
    }
}
