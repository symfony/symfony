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

use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

/**
 * Common features needed in controllers.
 *
 * Supports both autowiring trough setters and accessing services using a container.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait ControllerTrait
{
    /**
     * @return RouterInterface
     */
    protected function getRouter()
    {
        if (isset($this->container)) {
            return $this->container->get('router');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', RouterInterface::class));
    }

    /**
     * @return RequestStack
     */
    protected function getRequestStack()
    {
        if (isset($this->container)) {
            return $this->container->get('request_stack');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', RequestStack::class));
    }

    /**
     * @return HttpKernelInterface
     */
    protected function getHttpKernel()
    {
        if (isset($this->container)) {
            return $this->container->get('http_kernel');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', HttpKernelInterface::class));
    }

    /**
     * @return SerializerInterface
     */
    protected function getSerializer()
    {
        if (isset($this->container) && $this->container->has('serializer')) {
            return $this->container->get('serializer');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', SerializerInterface::class));
    }

    /**
     * Passing the Symfony session implementation is mandatory because flashes are not part of the interface.
     *
     * @return Session
     */
    protected function getSession()
    {
        if (isset($this->container) && $this->container->has('session')) {
            return $this->container->get('session');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', Session::class));
    }

    /**
     * @return AuthorizationCheckerInterface
     */
    protected function getAuthorizationChecker()
    {
        if (isset($this->container) && $this->container->has('security.authorization_checker')) {
            return $this->container->get('security.authorization_checker');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', AuthorizationCheckerInterface::class));
    }

    /**
     * @return EngineInterface
     */
    protected function getTemplating()
    {
        if (isset($this->container) && $this->container->has('templating')) {
            return $this->container->get('templating');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', EngineInterface::class));
    }

    /**
     * @return \Twig_Environment
     */
    protected function getTwig()
    {
        if (isset($this->container) && $this->container->has('twig')) {
            return $this->container->get('twig');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', \Twig_Environment::class));
    }

    /**
     * @return ManagerRegistry
     */
    protected function getDoctrine()
    {
        if (isset($this->container) && $this->container->has('doctrine')) {
            return $this->container->get('doctrine');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', ManagerRegistry::class));
    }

    /**
     * @return FormFactoryInterface
     */
    protected function getFormFactory()
    {
        if (isset($this->container)) {
            return $this->container->get('form.factory');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', FormFactoryInterface::class));
    }

    /**
     * @return TokenStorageInterface
     */
    protected function getTokenStorage()
    {
        if (isset($this->container) && $this->container->has('security.token_storage')) {
            return $this->container->get('security.token_storage');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', TokenStorageInterface::class));
    }

    /**
     * @return CsrfTokenManagerInterface
     */
    protected function getCsrfTokenManager()
    {
        if (isset($this->container) && $this->container->has('security.csrf.token_manager')) {
            return $this->container->get('security.csrf.token_manager');
        }

        throw new \LogicException(sprintf('An instance of "%s" must be provided.', CsrfTokenManagerInterface::class));
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
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @return Response A Response instance
     */
    protected function forward($controller, array $path = array(), array $query = array())
    {
        $request = $this->getRequestStack()->getCurrentRequest();
        $path['_forwarded'] = $request->attributes;
        $path['_controller'] = $controller;
        $subRequest = $request->duplicate($query, null, $path);

        return $this->getHttpKernel()->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

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
        return $this->redirect($this->generateUrl($route, $parameters), $status);
    }

    /**
     * Returns a JsonResponse that uses the serializer component if enabled, or json_encode.
     *
     * @param mixed $data    The response data
     * @param int   $status  The status code to use for the Response
     * @param array $headers Array of extra headers to add
     * @param array $context Context to pass to serializer when using serializer component
     *
     * @return JsonResponse
     */
    protected function json($data, $status = 200, $headers = array(), $context = array())
    {
        try {
            $json = $this->getSerializer()->serialize($data, 'json', array_merge(array(
                'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
            ), $context));

            return new JsonResponse($json, $status, $headers, true);
        } catch (\LogicException $e) {
            return new JsonResponse($data, $status, $headers);
        }
    }

    /**
     * Returns a BinaryFileResponse object with original or customized file name and disposition header.
     *
     * @param \SplFileInfo|string $file        File object or path to file to be sent as response
     * @param string|null         $fileName    File name to be sent to response or null (will use original file name)
     * @param string              $disposition Disposition of response ("attachment" is default, other type is "inline")
     *
     * @return BinaryFileResponse
     */
    protected function file($file, $fileName = null, $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT)
    {
        $response = new BinaryFileResponse($file);
        $response->setContentDisposition($disposition, $fileName === null ? $response->getFile()->getFilename() : $fileName);

        return $response;
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
        $this->getSession()->getFlashBag()->add($type, $message);
    }

    /**
     * Checks if the attributes are granted against the current authentication token and optionally supplied object.
     *
     * @param mixed $attributes The attributes
     * @param mixed $object     The object
     *
     * @return bool
     *
     * @throws \LogicException
     */
    protected function isGranted($attributes, $object = null)
    {
        return $this->getAuthorizationChecker()->isGranted($attributes, $object);
    }

    /**
     * Throws an exception unless the attributes are granted against the current authentication token and optionally
     * supplied object.
     *
     * @param mixed  $attributes The attributes
     * @param mixed  $object     The object
     * @param string $message    The message passed to the exception
     *
     * @throws AccessDeniedException
     */
    protected function denyAccessUnlessGranted($attributes, $object = null, $message = 'Access Denied.')
    {
        if (!$this->isGranted($attributes, $object)) {
            $exception = $this->createAccessDeniedException($message);
            $exception->setAttributes($attributes);
            $exception->setSubject($object);
            throw $exception;
        }
    }

    /**
     * Returns a rendered view.
     *
     * @param string $view       The view name
     * @param array  $parameters An array of parameters to pass to the view
     *
     * @return string The rendered view
     */
    protected function renderView($view, array $parameters = array())
    {
        try {
            return $this->getTemplating()->render($view, $parameters);
        } catch (\LogicException $e) {
            return $this->getTwig()->render($view, $parameters);
        }
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
    protected function render($view, array $parameters = array(), Response $response = null)
    {
        try {
            return $this->getTemplating()->renderResponse($view, $parameters, $response);
        } catch (\LogicException $e) {
            if (null === $response) {
                $response = new Response();
            }

            return $response->setContent($this->getTwig()->render($view, $parameters));
        }
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
    protected function stream($view, array $parameters = array(), StreamedResponse $response = null)
    {
        try {
            $templating = $this->getTemplating();

            $callback = function () use ($templating, $view, $parameters) {
                $templating->stream($view, $parameters);
            };
        } catch (\LogicException $e) {
            $twig = $this->getTwig();

            $callback = function () use ($twig, $view, $parameters) {
                $twig->display($view, $parameters);
            };
        }

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
    protected function createNotFoundException($message = 'Not Found', \Exception $previous = null)
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
    protected function createAccessDeniedException($message = 'Access Denied.', \Exception $previous = null)
    {
        return new AccessDeniedException($message, $previous);
    }

    /**
     * Creates and returns a Form instance from the type of the form.
     *
     * @param string $type    The fully qualified class name of the form type
     * @param mixed  $data    The initial data for the form
     * @param array  $options Options for the form
     *
     * @return Form
     */
    protected function createForm($type, $data = null, array $options = array())
    {
        return $this->getFormFactory()->create($type, $data, $options);
    }

    /**
     * Creates and returns a form builder instance.
     *
     * @param mixed $data    The initial data for the form
     * @param array $options Options for the form
     *
     * @return FormBuilder
     */
    protected function createFormBuilder($data = null, array $options = array())
    {
        return $this->getFormFactory()->createBuilder(FormType::class, $data, $options);
    }

    /**
     * Get a user from the Security Token Storage.
     *
     * @return mixed
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see TokenInterface::getUser()
     */
    protected function getUser()
    {
        if (null === $token = $this->getTokenStorage()->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }

    /**
     * Checks the validity of a CSRF token.
     *
     * @param string $id    The id used when generating the token
     * @param string $token The actual token sent with the request that should be validated
     *
     * @return bool
     */
    protected function isCsrfTokenValid($id, $token)
    {
        return $this->getCsrfTokenManager()->isTokenValid(new CsrfToken($id, $token));
    }
}
