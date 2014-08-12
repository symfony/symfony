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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Controller is a simple implementation of a Controller.
 *
 * It provides methods to common features needed in controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Controller extends ContainerAware
{
    /**
     * @var \Symfony\Bridge\Doctrine\RegistryInterface
     */
    public $doctrine;

    /**
     * @var \Symfony\Component\Form\FormFactoryInterface
     */
    public $formFactory;

    /**
     * @var \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    public $httpKernel;

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack
     */
    public $requestStack;

    /**
     * @var \Symfony\Component\Routing\RouterInterface
     */
    public $router;

    /**
     * @var \Symfony\Component\Security\Core\SecurityContextInterface
     */
    public $securityContext;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\Templating\EngineInterface
     */
    public $templating;

    /**
     * Inject services from the container until Symfony 3.0 cleans all
     * of that and stop injectig the service container into controllers
     *
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->doctrine        = $this->container->get('doctrine',         ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->formFactory     = $this->container->get('form.factory',     ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->httpKernel      = $this->container->get('http_kernel',      ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->requestStack    = $this->container->get('request_stack',    ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->router          = $this->container->get('router',           ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->securityContext = $this->container->get('security.context', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $this->templating      = $this->container->get('templating',       ContainerInterface::NULL_ON_INVALID_REFERENCE);
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @param string         $route         The name of the route
     * @param mixed          $parameters    An array of parameters
     * @param bool|string    $referenceType The type of reference (one of the constants in UrlGeneratorInterface)
     *
     * @return string The generated URL
     *
     * @see UrlGeneratorInterface
     */
    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @return Response A Response instance
     */
    public function forward($controller, array $path = array(), array $query = array())
    {
        $path['_controller'] = $controller;
        $subRequest = $this->requestStack->getCurrentRequest()->duplicate($query, null, $path);

        return $this->httpKernel->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Returns a RedirectResponse to the given URL.
     *
     * @param string  $url    The URL to redirect to
     * @param int     $status The status code to use for the Response
     *
     * @return RedirectResponse
     */
    public function redirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Returns a rendered view.
     *
     * @param string $view       The view name
     * @param array  $parameters An array of parameters to pass to the view
     *
     * @return string The rendered view
     */
    public function renderView($view, array $parameters = array())
    {
        return $this->templating->render($view, $parameters);
    }

    /**
     * Renders a view.
     *
     * @param string   $view       The view name
     * @param array    $parameters An array of parameters to pass to the view
     * @param Response $response   A response instance
     *
     * @return Response A Response instance
     */
    public function render($view, array $parameters = array(), Response $response = null)
    {
        return $this->templating->renderResponse($view, $parameters, $response);
    }

    /**
     * Streams a view.
     *
     * @param string           $view       The view name
     * @param array            $parameters An array of parameters to pass to the view
     * @param StreamedResponse $response   A response instance
     *
     * @return StreamedResponse A StreamedResponse instance
     */
    public function stream($view, array $parameters = array(), StreamedResponse $response = null)
    {
        $templating = $this->templating;

        $callback = function () use ($templating, $view, $parameters) {
            $templating->stream($view, $parameters);
        };

        if (null === $response) {
            return new StreamedResponse($callback);
        }

        $response->setCallback($callback);

        return $response;
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
    public function createNotFoundException($message = 'Not Found', \Exception $previous = null)
    {
        return new NotFoundHttpException($message, $previous);
    }

    /**
     * Returns an AccessDeniedException.
     *
     * This will result in a 403 response code. Usage example:
     *
     *     throw $this->createAccessDeniedException('Unable to access this page!');
     *
     * @param string          $message  A message
     * @param \Exception|null $previous The previous exception
     *
     * @return AccessDeniedException
     */
    public function createAccessDeniedException($message = 'Access Denied', \Exception $previous = null)
    {
        return new AccessDeniedException($message, $previous);
    }

    /**
     * Creates and returns a Form instance from the type of the form.
     *
     * @param string|FormTypeInterface $type    The built type of the form
     * @param mixed                    $data    The initial data for the form
     * @param array                    $options Options for the form
     *
     * @return Form
     */
    public function createForm($type, $data = null, array $options = array())
    {
        return $this->formFactory->create($type, $data, $options);
    }

    /**
     * Creates and returns a form builder instance
     *
     * @param mixed $data    The initial data for the form
     * @param array $options Options for the form
     *
     * @return FormBuilder
     */
    public function createFormBuilder($data = null, array $options = array())
    {
        return $this->formFactory->createBuilder('form', $data, $options);
    }

    /**
     * Shortcut to return the request service.
     *
     * @return Request
     *
     * @deprecated Deprecated since version 2.4, to be removed in 3.0. Ask
     *             Symfony to inject the Request object into your controller
     *             method instead by type hinting it in the method's signature.
     */
    public function getRequest()
    {
        return $this->requestStack->getCurrentRequest();
    }

    /**
     * Shortcut to return the Doctrine Registry service.
     *
     * @return RegistryInterface
     *
     * @throws \LogicException If DoctrineBundle is not available
     */
    public function getDoctrine()
    {
        if (!isset($this->doctrine)) {
            throw new \LogicException('The DoctrineBundle is not registered in your application.');
        }

        return $this->doctrine;
    }

    /**
     * Get a user from the Security Context
     *
     * @return mixed
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see Symfony\Component\Security\Core\Authentication\Token\TokenInterface::getUser()
     */
    public function getUser()
    {
        if (!isset($this->securityContext)) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->securityContext->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            return;
        }

        return $user;
    }

    /**
     * Returns true if the service id is defined.
     *
     * @param string $id The service id
     *
     * @return bool    true if the service id is defined, false otherwise
     *
     * @deprecated Deprecated since version 2.6, to be removed in 3.0. Ask
     *             Symfony to inject the correct service into your controller
     *             instead of injectig the whole service container which is a
     *             really bad practice
     */
    public function has($id)
    {
        return $this->container->has($id);
    }

    /**
     * Gets a service by id.
     *
     * @param string $id The service id
     *
     * @return object The service
     *
     * @deprecated Deprecated since version 2.6, to be removed in 3.0. Ask
     *             Symfony to inject the correct service into your controller
     *             instead of injectig the whole service container which is a
     *             really bad practice
     */
    public function get($id)
    {
        return $this->container->get($id);
    }

    /**
     * @param \Symfony\Bridge\Doctrine\RegistryInterface $doctrine
     */
    public function setDoctrine($doctrine)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @param \Symfony\Component\Form\FormFactoryInterface $formFactory
     */
    public function setFormFactory($formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * @param \Symfony\Component\HttpKernel\HttpKernelInterface $httpKernel
     */
    public function setHttpKernel($httpKernel)
    {
        $this->httpKernel = $httpKernel;
    }

    /**
     * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
     */
    public function setRequestStack($requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * @param \Symfony\Component\Routing\RouterInterface $router
     */
    public function setRouter($router)
    {
        $this->router = $router;
    }

    /**
     * @param \Symfony\Component\Security\Core\SecurityContextInterface $securityContext
     */
    public function setSecurityContext($securityContext)
    {
        $this->securityContext = $securityContext;
    }

    /**
     * @param \Symfony\Bundle\FrameworkBundle\Templating\EngineInterface $templating
     */
    public function setTemplating($templating)
    {
        $this->templating = $templating;
    }
}
