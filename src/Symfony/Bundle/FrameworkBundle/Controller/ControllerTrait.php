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

use Doctrine\Bundle\DoctrineBundle\Registry;
use Symfony\Bundle\FrameworkBundle\Form\FormHelperTrait;
use Symfony\Bundle\FrameworkBundle\Kernel\KernelHelperTrait;
use Symfony\Bundle\FrameworkBundle\Routing\RouterHelperTrait;
use Symfony\Bundle\FrameworkBundle\Security\SecurityHelperTrait;
use Symfony\Bundle\FrameworkBundle\Serializer\SerializerHelperTrait;
use Symfony\Bundle\FrameworkBundle\Templating\RenderHelperTrait;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ControllerTrait is a simple implementation of a Controller.
 *
 * It provides methods to common features needed in controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
trait ControllerTrait
{
    use ContainerAwareTrait;
    use KernelHelperTrait;
    use RouterHelperTrait;
    use FormHelperTrait;
    use RenderHelperTrait;
    use SerializerHelperTrait;
    use SecurityHelperTrait;

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param string $url    The URL to redirect to
     * @param int    $status The status code to use for the Response
     *
     * @return RedirectResponse
     */
    protected function redirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Adds a flash message to the current session for type.
     *
     * @param string $type    The type
     * @param string $message The message
     *
     * @throws \LogicException
     */
    protected function addFlash($type, $message)
    {
        if (!$this->container->has('session')) {
            throw new \LogicException('You can not use the addFlash method if sessions are disabled.');
        }

        $this->container->get('session')->getFlashBag()->add($type, $message);
    }

    /**
     * Returns a NotFoundHttpException.
     *
     * This will result in a 404 response code. Usage example:
     *
     *     throw $this->createNotFoundException('Page not found!');
     *
     * @param string          $message  A message
     * @param \Exception|null $previous The previous exception
     *
     * @return NotFoundHttpException
     */
    protected function createNotFoundException($message = 'Not Found', \Exception $previous = null)
    {
        return new NotFoundHttpException($message, $previous);
    }

    /**
     * Shortcut to return the Doctrine Registry service.
     *
     * @return Registry
     *
     * @throws \LogicException If DoctrineBundle is not available
     */
    protected function getDoctrine()
    {
        if (!$this->container->has('doctrine')) {
            throw new \LogicException('The DoctrineBundle is not registered in your application.');
        }

        return $this->container->get('doctrine');
    }

    /**
     * Returns true if the service id is defined.
     *
     * @param string $id The service id
     *
     * @return bool true if the service id is defined, false otherwise
     */
    protected function has($id)
    {
        return $this->container->has($id);
    }

    /**
     * Gets a container service by its id.
     *
     * @param string $id The service id
     *
     * @return object The service
     */
    protected function get($id)
    {
        return $this->container->get($id);
    }

    /**
     * Gets a container configuration parameter by its name.
     *
     * @param string $name The parameter name
     *
     * @return mixed
     */
    protected function getParameter($name)
    {
        return $this->container->getParameter($name);
    }
}
