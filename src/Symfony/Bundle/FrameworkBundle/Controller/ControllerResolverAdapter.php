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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ControllerFactoryInterface;

/**
 * @author Iltar van der Berg <kjarli@gmail.com>
 *
 * @internal only used to work around a circular dependency in a BC layer.
 */
final class ControllerResolverAdapter implements ControllerFactoryInterface
{
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function createFromString($controller)
    {
        return $this->container->get('obfuscated_controller_resolver')->getController(new Request(array(), array(), array('_controller' => $controller)));
    }
}
