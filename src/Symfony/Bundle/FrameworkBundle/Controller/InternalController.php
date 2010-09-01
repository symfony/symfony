<?php

namespace Symfony\Bundle\FrameworkBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * InternalController.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class InternalController extends ContainerAware
{
    /**
     * Forwards to the given controller with the given path.
     *
     * @param string $path       The path
     * @param string $controller The controller name
     *
     * @return Response A Response instance
     */
    public function indexAction($path, $controller)
    {
        $request = $this->container->get('request');
        $attributes = $request->attributes;

        $attributes->delete('path');
        $attributes->delete('controller');
        if ('none' !== $path)
        {
            parse_str($path, $tmp);
            $attributes->add($tmp);
        }

        return $this->container->get('controller_resolver')->forward($controller, $attributes->all(), $request->query->all());
    }
}
