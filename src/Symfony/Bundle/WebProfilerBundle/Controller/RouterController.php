<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\RequestDataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class RouterController
{
    private $profiler;
    private $twig;
    private $matcher;
    private $routes;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = [];

    public function __construct(Profiler $profiler = null, Environment $twig, UrlMatcherInterface $matcher = null, RouteCollection $routes = null, iterable $expressionLanguageProviders = [])
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->matcher = $matcher;
        $this->routes = (null === $routes && $matcher instanceof RouterInterface) ? $matcher->getRouteCollection() : $routes;
        $this->expressionLanguageProviders = $expressionLanguageProviders;
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @throws NotFoundHttpException
     */
    public function panelAction(string $token): Response
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null === $this->matcher || null === $this->routes) {
            return new Response('The Router is not enabled.', 200, ['Content-Type' => 'text/html']);
        }

        $profile = $this->profiler->loadProfile($token);

        /** @var RequestDataCollector $request */
        $request = $profile->getCollector('request');

        return new Response($this->twig->render('@WebProfiler/Router/panel.html.twig', [
            'request' => $request,
            'router' => $profile->getCollector('router'),
            'traces' => $this->getTraces($request, $profile->getMethod()),
        ]), 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Returns the routing traces associated to the given request.
     */
    private function getTraces(RequestDataCollector $request, string $method): array
    {
        $traceRequest = Request::create(
            $request->getPathInfo(),
            $request->getRequestServer(true)->get('REQUEST_METHOD'),
            \in_array($request->getMethod(), ['DELETE', 'PATCH', 'POST', 'PUT'], true) ? $request->getRequestRequest()->all() : $request->getRequestQuery()->all(),
            $request->getRequestCookies(true)->all(),
            [],
            $request->getRequestServer(true)->all()
        );

        $context = $this->matcher->getContext();
        $context->setMethod($method);
        $matcher = new TraceableUrlMatcher($this->routes, $context);
        foreach ($this->expressionLanguageProviders as $provider) {
            $matcher->addExpressionLanguageProvider($provider);
        }

        return $matcher->getTracesForRequest($traceRequest);
    }
}
