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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Common features needed in controllers.
 *
 * The recommended way of injecting dependencies is trough getter injection.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 *
 * @experimental in version 3.3
 */
trait ControllerTrait
{
    protected function getRouter(): RouterInterface
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', RouterInterface::class));
    }

    protected function getRequestStack(): RequestStack
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', RequestStack::class));
    }

    protected function getHttpKernel(): HttpKernelInterface
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', HttpKernelInterface::class));
    }

    protected function getSerializer(): SerializerInterface
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', SerializerInterface::class));
    }

    /**
     * An instance of the Session implementation (and not the interface) is returned because getFlashBag is not part of
     * the interface.
     *
     * @return Session
     */
    protected function getSession(): Session
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', Session::class));
    }

    protected function getAuthorizationChecker(): AuthorizationCheckerInterface
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', AuthorizationCheckerInterface::class));
    }

    protected function getTwig(): \Twig_Environment
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', \Twig_Environment::class));
    }

    protected function getDoctrine(): ManagerRegistry
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', ManagerRegistry::class));
    }

    protected function getFormFactory(): FormFactoryInterface
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', FormFactoryInterface::class));
    }

    protected function getTokenStorage(): TokenStorageInterface
    {
        throw new \LogicException(sprintf('An instance of "%s" must be provided.', TokenStorageInterface::class));
    }

    protected function getCsrfTokenManager(): CsrfTokenManagerInterface
    {
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
    protected function generateUrl(string $route, array $parameters = array(), int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
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
    protected function forward(string $controller, array $path = array(), array $query = array()): Response
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
    protected function redirect(string $url, int $status = 302): RedirectResponse
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
    protected function redirectToRoute(string $route, array $parameters = array(), int $status = 302): RedirectResponse
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
    protected function json($data, int $status = 200, array $headers = array(), array $context = array()): JsonResponse
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
    protected function file($file, string $fileName = null, string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): BinaryFileResponse
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
    protected function addFlash(string $type, string $message)
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
    protected function isGranted($attributes, $object = null): bool
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
    protected function denyAccessUnlessGranted($attributes, $object = null, string $message = 'Access Denied.')
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
    protected function renderView(string $view, array $parameters = array()): string
    {
        return $this->getTwig()->render($view, $parameters);
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
    protected function render(string $view, array $parameters = array(), Response $response = null): Response
    {
        if (null === $response) {
            $response = new Response();
        }

        return $response->setContent($this->getTwig()->render($view, $parameters));
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
    protected function stream(string $view, array $parameters = array(), StreamedResponse $response = null): StreamedResponse
    {
        $twig = $this->getTwig();

        $callback = function () use ($twig, $view, $parameters) {
            $twig->display($view, $parameters);
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
    protected function createNotFoundException(string $message = 'Not Found', \Exception $previous = null): NotFoundHttpException
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
    protected function createAccessDeniedException(string $message = 'Access Denied.', \Exception $previous = null): AccessDeniedException
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
     * @return FormInterface
     */
    protected function createForm(string $type, $data = null, array $options = array()): FormInterface
    {
        return $this->getFormFactory()->create($type, $data, $options);
    }

    /**
     * Creates and returns a form builder instance.
     *
     * @param mixed $data    The initial data for the form
     * @param array $options Options for the form
     *
     * @return FormBuilderInterface
     */
    protected function createFormBuilder($data = null, array $options = array()): FormBuilderInterface
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
    protected function isCsrfTokenValid(string $id, string $token): bool
    {
        return $this->getCsrfTokenManager()->isTokenValid(new CsrfToken($id, $token));
    }
}
