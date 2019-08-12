<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Controller;

use Doctrine\Common\Persistence\ManagerRegistry;
use Psr\Link\LinkInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class TestAbstractController extends AbstractController
{
    private $throwOnUnexpectedService;

    public function __construct($throwOnUnexpectedService = true)
    {
        $this->throwOnUnexpectedService = $throwOnUnexpectedService;
    }

    public function setContainer(ContainerInterface $container)
    {
        if (!$this->throwOnUnexpectedService) {
            return parent::setContainer($container);
        }

        $expected = self::getSubscribedServices();

        foreach ($container->getServiceIds() as $id) {
            if ('service_container' === $id) {
                continue;
            }
            if (!isset($expected[$id])) {
                throw new \UnexpectedValueException(sprintf('Service "%s" is not expected, as declared by %s::getSubscribedServices()', $id, AbstractController::class));
            }
            $type = substr($expected[$id], 1);
            if (!$container->get($id) instanceof $type) {
                throw new \UnexpectedValueException(sprintf('Service "%s" is expected to be an instance of "%s", as declared by %s::getSubscribedServices()', $id, $type, AbstractController::class));
            }
        }

        return parent::setContainer($container);
    }

    public function getParameter(string $name)
    {
        return parent::getParameter($name);
    }

    public function fooAction()
    {
    }

    public function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return parent::generateUrl($route, $parameters, $referenceType);
    }

    public function forward(string $controller, array $path = [], array $query = []): Response
    {
        return parent::forward($controller, $path, $query);
    }

    public function redirect(string $url, int $status = 302): RedirectResponse
    {
        return parent::redirect($url, $status);
    }

    public function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return parent::redirectToRoute($route, $parameters, $status);
    }

    public function json($data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json($data, $status, $headers, $context);
    }

    public function file($file, string $fileName = null, string $disposition = ResponseHeaderBag::DISPOSITION_ATTACHMENT): BinaryFileResponse
    {
        return parent::file($file, $fileName, $disposition);
    }

    public function addFlash(string $type, string $message): void
    {
        parent::addFlash($type, $message);
    }

    public function isGranted($attributes, $subject = null): bool
    {
        return parent::isGranted($attributes, $subject);
    }

    public function denyAccessUnlessGranted($attributes, $subject = null, string $message = 'Access Denied.'): void
    {
        parent::denyAccessUnlessGranted($attributes, $subject, $message);
    }

    public function renderView(string $view, array $parameters = []): string
    {
        return parent::renderView($view, $parameters);
    }

    public function render(string $view, array $parameters = [], Response $response = null): Response
    {
        return parent::render($view, $parameters, $response);
    }

    public function stream(string $view, array $parameters = [], StreamedResponse $response = null): StreamedResponse
    {
        return parent::stream($view, $parameters, $response);
    }

    public function createNotFoundException(string $message = 'Not found', \Throwable $previous = null): NotFoundHttpException
    {
        return parent::createNotFoundException($message, $previous);
    }

    public function createAccessDeniedException(string $message = 'Access Denied.', \Throwable $previous = null): AccessDeniedException
    {
        return parent::createAccessDeniedException($message, $previous);
    }

    public function createForm(string $type, $data = null, array $options = []): FormInterface
    {
        return parent::createForm($type, $data, $options);
    }

    public function createFormBuilder($data = null, array $options = []): FormBuilderInterface
    {
        return parent::createFormBuilder($data, $options);
    }

    public function getDoctrine(): ManagerRegistry
    {
        return parent::getDoctrine();
    }

    public function getUser()
    {
        return parent::getUser();
    }

    public function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return parent::isCsrfTokenValid($id, $token);
    }

    public function dispatchMessage($message, array $stamps = []): Envelope
    {
        return parent::dispatchMessage($message, $stamps);
    }

    public function addLink(Request $request, LinkInterface $link): void
    {
        parent::addLink($request, $link);
    }
}
