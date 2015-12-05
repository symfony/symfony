<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Symfony\Bundle\FrameworkBundle\Exception\LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * A collection of helper functions for classes using the router.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait RouterHelperTrait
{
    /**
     * @var UrlGeneratorInterface
     */
    protected $router;

    /**
     * @return UrlGeneratorInterface
     */
    protected function getRouter()
    {
        if ($this->router === null) {
            if (!isset($this->container)) {
                throw new LogicException('Unable to retrieve the router.');
            }

            $this->router = $this->container->get('router');
        }

        return $this->router;
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param string $route         The name of the route
     * @param mixed  $parameters    An array of parameters
     * @param int    $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The generated URL
     *
     * @see UrlGeneratorInterface
     */
    protected function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->getRouter()->generate($route, $parameters, $referenceType);
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     *
     * @param string $route      The name of the route
     * @param array  $parameters An array of parameters
     * @param int    $status     The status code to use for the Response
     *
     * @return RedirectResponse
     */
    protected function redirectToRoute($route, array $parameters = array(), $status = 302)
    {
        return new RedirectResponse($this->generateUrl($route, $parameters), $status);
    }
}
