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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;

/**
 * InternalController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
        // safeguard
        if (!is_string($controller)) {
            throw new \RuntimeException('A Controller must be a string.');
        }

        // check that the controller looks like a controller
        if (false === strpos($controller, '::')) {
            $count = substr_count($controller, ':');
            if (2 == $count) {
                // the convention already enforces the Controller suffix
            } elseif (1 == $count) {
                // controller in the service:method notation
                list($service, $method) = explode(':', $controller, 2);
                $class = get_class($this->container->get($service));

                if (!preg_match('/Controller$/', $class)) {
                    throw new \RuntimeException('A Controller class name must end with Controller.');
                }
            } else {
                throw new \LogicException('Unable to parse the Controller name.');
            }
        } else {
            list($class, $method) = explode('::', $controller, 2);

            if (!preg_match('/Controller$/', $class)) {
                throw new \RuntimeException('A Controller class name must end with Controller.');
            }
        }

        $request = $this->container->get('request');
        $attributes = $request->attributes;

        $attributes->remove('path');
        $attributes->remove('controller');
        if ('none' !== $path) {
            parse_str($path, $tmp);
            $attributes->add($tmp);
        }

        return $this->container->get('http_kernel')->forward($controller, $attributes->all(), $request->query->all());
    }
}
