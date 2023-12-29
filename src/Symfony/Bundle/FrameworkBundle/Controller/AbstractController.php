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

use Doctrine\Persistence\ManagerRegistry;
use Psr\Container\ContainerInterface;
use Psr\Link\LinkInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\WebLink\EventListener\AddLinkHeaderListener;
use Symfony\Component\WebLink\GenericLinkProvider;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Environment;

/**
 * Provides shortcuts for HTTP-related features in controllers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractController implements ServiceSubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @required
     */
    public function setContainer(ContainerInterface $container): ?ContainerInterface
    {
        $previous = $this->container;
        $this->container = $container;

        return $previous;
    }

    /**
     * Gets a container parameter by its name.
     *
     * @return array|bool|float|int|string|\UnitEnum|null
     */
    protected function getParameter(string $name)
    {
        if (!$this->container->has('parameter_bag')) {
            throw new ServiceNotFoundException('parameter_bag.', null, null, [], sprintf('The "%s::getParameter()" method is missing a parameter bag to work properly. Did you forget to register your controller as a service subscriber? This can be fixed either by using autoconfiguration or by manually wiring a "parameter_bag" in the service locator passed to the controller.', static::class));
        }

        return $this->container->get('parameter_bag')->get($name);
    }

    public static function getSubscribedServices()
    {
        return [
            'router' => '?'.RouterInterface::class,
            'request_stack' => '?'.RequestStack::class,
            'http_kernel' => '?'.HttpKernelInterface::class,
            'serializer' => '?'.SerializerInterface::class,
            'session' => '?'.SessionInterface::class,
            'security.authorization_checker' => '?'.AuthorizationCheckerInterface::class,
            'twig' => '?'.Environment::class,
            'doctrine' => '?'.ManagerRegistry::class, // to be removed in 6.0
            'form.factory' => '?'.FormFactoryInterface::class,
            'security.token_storage' => '?'.TokenStorageInterface::class,
            'security.csrf.token_manager' => '?'.CsrfTokenManagerInterface::class,
            'parameter_bag' => '?'.ContainerBagInterface::class,
            'message_bus' => '?'.MessageBusInterface::class, // to be removed in 6.0
            'messenger.default_bus' => '?'.MessageBusInterface::class, // to be removed in 6.0
        ];
    }

    /**
     * Returns true if the service id is defined.
     *
     * @deprecated since Symfony 5.4, use method or constructor injection in your controller instead
     */
    protected function has(string $id): bool
    {
        trigger_deprecation('symfony/framework-bundle', '5.4', 'Method "%s()" is deprecated, use method or constructor injection in your controller instead.', __METHOD__);

        return $this->container->has($id);
    }

    /**
     * Gets a container service by its id.
     *
     * @return object The service
     *
     * @deprecated since Symfony 5.4, use method or constructor injection in your controller instead
     */
    protected function get(string $id): object
    {
        trigger_deprecation('symfony/framework-bundle', '5.4', 'Method "%s()" is deprecated, use method or constructor injection in your controller instead.', __METHOD__);

        return $this->container->get($id);
    }

    /**
     * Generates a URL from the given parameters.
     *
     * @see UrlGeneratorInterface
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->container->get('router')->generate($route, $parameters, $referenceType);
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like "App\Controller\PostController::index" or "App\Controller\PostController" if it is invokable)
     */
    protected function forward(string $controller, array $path = [], array $query = []): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        $path['_controller'] = $controller;
        $subRequest = $request->duplicate($query, null, $path);

        return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * Returns a RedirectResponse to the given URL.
     */
    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Returns a RedirectResponse to the given route with the given parameters.
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return $this->redirect($this->generateUrl($route, $parameters), $status);
    }

    /**
     * Returns a JsonResponse that uses the serializer component if enabled, or json_encode.
     */
    protected function json($data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        if ($this->container->has('serializer')) {
            $json = $this->container->get('serializer')->serialize($data, 'json', array_merge([
                'json_encode_options' => JsonResponse::DEFAULT_ENCODING_OPTIONS,
            ], $context));

            return new JsonResponse($json, $status, $headers, true);
        }

        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Returns a BinaryFileResponse object with original or customized file name and disposition header.
     *
     * @param \SplFileInfo|string $file File object or path to file to be sent as response
     */
    protected function file($file, string $fileName = null, string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): BinaryFileResponse
    {
        $response = new BinaryFileResponse($file);
        $response->setContentDisposition($disposition, null === $fileName ? $response->getFile()->getFilename() : $fileName);

        return $response;
    }

    /**
     * Adds a flash message to the current session for type.
     *
     * @throws \LogicException
     */
    protected function addFlash(string $type, $message): void
    {
        try {
            $this->container->get('request_stack')->getSession()->getFlashBag()->add($type, $message);
        } catch (SessionNotFoundException $e) {
            throw new \LogicException('You cannot use the addFlash method if sessions are disabled. Enable them in "config/packages/framework.yaml".', 0, $e);
        }
    }

    /**
     * Checks if the attribute is granted against the current authentication token and optionally supplied subject.
     *
     * @throws \LogicException
     */
    protected function isGranted($attribute, $subject = null): bool
    {
        if (!$this->container->has('security.authorization_checker')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }

        return $this->container->get('security.authorization_checker')->isGranted($attribute, $subject);
    }

    /**
     * Throws an exception unless the attribute is granted against the current authentication token and optionally
     * supplied subject.
     *
     * @throws AccessDeniedException
     */
    protected function denyAccessUnlessGranted($attribute, $subject = null, string $message = 'Access Denied.'): void
    {
        if (!$this->isGranted($attribute, $subject)) {
            $exception = $this->createAccessDeniedException($message);
            $exception->setAttributes([$attribute]);
            $exception->setSubject($subject);

            throw $exception;
        }
    }

    /**
     * Returns a rendered view.
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        if (!$this->container->has('twig')) {
            throw new \LogicException('You cannot use the "renderView" method if the Twig Bundle is not available. Try running "composer require symfony/twig-bundle".');
        }

        return $this->container->get('twig')->render($view, $parameters);
    }

    /**
     * Renders a view.
     */
    protected function render(string $view, array $parameters = [], Response $response = null): Response
    {
        $content = $this->renderView($view, $parameters);

        if (null === $response) {
            $response = new Response();
        }

        $response->setContent($content);

        return $response;
    }

    /**
     * Renders a view and sets the appropriate status code when a form is listed in parameters.
     *
     * If an invalid form is found in the list of parameters, a 422 status code is returned.
     */
    protected function renderForm(string $view, array $parameters = [], Response $response = null): Response
    {
        if (null === $response) {
            $response = new Response();
        }

        foreach ($parameters as $k => $v) {
            if ($v instanceof FormView) {
                throw new \LogicException(sprintf('Passing a FormView to "%s::renderForm()" is not supported, pass directly the form instead for parameter "%s".', get_debug_type($this), $k));
            }

            if (!$v instanceof FormInterface) {
                continue;
            }

            $parameters[$k] = $v->createView();

            if (200 === $response->getStatusCode() && $v->isSubmitted() && !$v->isValid()) {
                $response->setStatusCode(422);
            }
        }

        return $this->render($view, $parameters, $response);
    }

    /**
     * Streams a view.
     */
    protected function stream(string $view, array $parameters = [], StreamedResponse $response = null): StreamedResponse
    {
        if (!$this->container->has('twig')) {
            throw new \LogicException('You cannot use the "stream" method if the Twig Bundle is not available. Try running "composer require symfony/twig-bundle".');
        }

        $twig = $this->container->get('twig');

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
     */
    protected function createNotFoundException(string $message = 'Not Found', \Throwable $previous = null): NotFoundHttpException
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
     * @throws \LogicException If the Security component is not available
     */
    protected function createAccessDeniedException(string $message = 'Access Denied.', \Throwable $previous = null): AccessDeniedException
    {
        if (!class_exists(AccessDeniedException::class)) {
            throw new \LogicException('You cannot use the "createAccessDeniedException" method if the Security component is not available. Try running "composer require symfony/security-bundle".');
        }

        return new AccessDeniedException($message, $previous);
    }

    /**
     * Creates and returns a Form instance from the type of the form.
     */
    protected function createForm(string $type, $data = null, array $options = []): FormInterface
    {
        return $this->container->get('form.factory')->create($type, $data, $options);
    }

    /**
     * Creates and returns a form builder instance.
     */
    protected function createFormBuilder($data = null, array $options = []): FormBuilderInterface
    {
        return $this->container->get('form.factory')->createBuilder(FormType::class, $data, $options);
    }

    /**
     * Shortcut to return the Doctrine Registry service.
     *
     * @throws \LogicException If DoctrineBundle is not available
     *
     * @deprecated since Symfony 5.4, inject an instance of ManagerRegistry in your controller instead
     */
    protected function getDoctrine(): ManagerRegistry
    {
        trigger_deprecation('symfony/framework-bundle', '5.4', 'Method "%s()" is deprecated, inject an instance of ManagerRegistry in your controller instead.', __METHOD__);

        if (!$this->container->has('doctrine')) {
            throw new \LogicException('The DoctrineBundle is not registered in your application. Try running "composer require symfony/orm-pack".');
        }

        return $this->container->get('doctrine');
    }

    /**
     * Get a user from the Security Token Storage.
     *
     * @return UserInterface|null
     *
     * @throws \LogicException If SecurityBundle is not available
     *
     * @see TokenInterface::getUser()
     */
    protected function getUser()
    {
        if (!$this->container->has('security.token_storage')) {
            throw new \LogicException('The SecurityBundle is not registered in your application. Try running "composer require symfony/security-bundle".');
        }

        if (null === $token = $this->container->get('security.token_storage')->getToken()) {
            return null;
        }

        // @deprecated since 5.4, $user will always be a UserInterface instance
        if (!\is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return null;
        }

        return $user;
    }

    /**
     * Checks the validity of a CSRF token.
     *
     * @param string      $id    The id used when generating the token
     * @param string|null $token The actual token sent with the request that should be validated
     */
    protected function isCsrfTokenValid(string $id, ?string $token): bool
    {
        if (!$this->container->has('security.csrf.token_manager')) {
            throw new \LogicException('CSRF protection is not enabled in your application. Enable it with the "csrf_protection" key in "config/packages/framework.yaml".');
        }

        return $this->container->get('security.csrf.token_manager')->isTokenValid(new CsrfToken($id, $token));
    }

    /**
     * Dispatches a message to the bus.
     *
     * @param object|Envelope $message The message or the message pre-wrapped in an envelope
     *
     * @deprecated since Symfony 5.4, inject an instance of MessageBusInterface in your controller instead
     */
    protected function dispatchMessage(object $message, array $stamps = []): Envelope
    {
        trigger_deprecation('symfony/framework-bundle', '5.4', 'Method "%s()" is deprecated, inject an instance of MessageBusInterface in your controller instead.', __METHOD__);

        if (!$this->container->has('messenger.default_bus')) {
            $message = class_exists(Envelope::class) ? 'You need to define the "messenger.default_bus" configuration option.' : 'Try running "composer require symfony/messenger".';
            throw new \LogicException('The message bus is not enabled in your application. '.$message);
        }

        return $this->container->get('messenger.default_bus')->dispatch($message, $stamps);
    }

    /**
     * Adds a Link HTTP header to the current response.
     *
     * @see https://tools.ietf.org/html/rfc5988
     */
    protected function addLink(Request $request, LinkInterface $link): void
    {
        if (!class_exists(AddLinkHeaderListener::class)) {
            throw new \LogicException('You cannot use the "addLink" method if the WebLink component is not available. Try running "composer require symfony/web-link".');
        }

        if (null === $linkProvider = $request->attributes->get('_links')) {
            $request->attributes->set('_links', new GenericLinkProvider([$link]));

            return;
        }

        $request->attributes->set('_links', $linkProvider->withLink($link));
    }
}
