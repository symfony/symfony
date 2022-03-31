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

use Symfony\Bundle\FullStack;
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class ProfilerController
{
    private $templateManager;
    private $generator;
    private $profiler;
    private $twig;
    private $templates;
    private $cspHandler;
    private $baseDir;

    public function __construct(UrlGeneratorInterface $generator, Profiler $profiler = null, Environment $twig, array $templates, ContentSecurityPolicyHandler $cspHandler = null, string $baseDir = null)
    {
        $this->generator = $generator;
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->templates = $templates;
        $this->cspHandler = $cspHandler;
        $this->baseDir = $baseDir;
    }

    /**
     * Redirects to the last profiles.
     *
     * @throws NotFoundHttpException
     */
    public function homeAction(): RedirectResponse
    {
        $this->denyAccessIfProfilerDisabled();

        return new RedirectResponse($this->generator->generate('_profiler_search_results', ['token' => 'empty', 'limit' => 10]), 302, ['Content-Type' => 'text/html']);
    }

    /**
     * Renders a profiler panel for the given token.
     *
     * @throws NotFoundHttpException
     */
    public function panelAction(Request $request, string $token): Response
    {
        $this->denyAccessIfProfilerDisabled();

        $this->cspHandler?->disableCsp();

        $panel = $request->query->get('panel');
        $page = $request->query->get('page', 'home');

        if ('latest' === $token && $latest = current($this->profiler->find(null, null, 1, null, null, null))) {
            $token = $latest['token'];
        }

        if (!$profile = $this->profiler->loadProfile($token)) {
            return $this->renderWithCspNonces($request, '@WebProfiler/Profiler/info.html.twig', ['about' => 'no_token', 'token' => $token, 'request' => $request]);
        }

        if (null === $panel) {
            $panel = 'request';

            foreach ($profile->getCollectors() as $collector) {
                if ($collector instanceof ExceptionDataCollector && $collector->hasException()) {
                    $panel = $collector->getName();

                    break;
                }

                if ($collector instanceof DumpDataCollector && $collector->getDumpsCount() > 0) {
                    $panel = $collector->getName();
                }
            }
        }

        if (!$profile->hasCollector($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not available for token "%s".', $panel, $token));
        }

        return $this->renderWithCspNonces($request, $this->getTemplateManager()->getName($profile, $panel), [
            'token' => $token,
            'profile' => $profile,
            'collector' => $profile->getCollector($panel),
            'panel' => $panel,
            'page' => $page,
            'request' => $request,
            'templates' => $this->getTemplateManager()->getNames($profile),
            'is_ajax' => $request->isXmlHttpRequest(),
            'profiler_markup_version' => 2, // 1 = original profiler, 2 = Symfony 2.8+ profiler
        ]);
    }

    /**
     * Renders the Web Debug Toolbar.
     *
     * @throws NotFoundHttpException
     */
    public function toolbarAction(Request $request, string $token = null): Response
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        if ($request->hasSession() && ($session = $request->getSession())->isStarted() && $session->getFlashBag() instanceof AutoExpireFlashBag) {
            // keep current flashes for one more request if using AutoExpireFlashBag
            $session->getFlashBag()->setAll($session->getFlashBag()->peekAll());
        }

        if ('empty' === $token || null === $token) {
            return new Response('', 200, ['Content-Type' => 'text/html']);
        }

        $this->profiler->disable();

        if (!$profile = $this->profiler->loadProfile($token)) {
            return new Response('', 404, ['Content-Type' => 'text/html']);
        }

        $url = null;
        try {
            $url = $this->generator->generate('_profiler', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Exception) {
            // the profiler is not enabled
        }

        return $this->renderWithCspNonces($request, '@WebProfiler/Profiler/toolbar.html.twig', [
            'full_stack' => class_exists(FullStack::class),
            'request' => $request,
            'profile' => $profile,
            'templates' => $this->getTemplateManager()->getNames($profile),
            'profiler_url' => $url,
            'token' => $token,
            'profiler_markup_version' => 2, // 1 = original toolbar, 2 = Symfony 2.8+ toolbar
        ]);
    }

    /**
     * Renders the profiler search bar.
     *
     * @throws NotFoundHttpException
     */
    public function searchBarAction(Request $request): Response
    {
        $this->denyAccessIfProfilerDisabled();

        $this->cspHandler?->disableCsp();

        if (!$request->hasSession()) {
            $ip =
            $method =
            $statusCode =
            $url =
            $start =
            $end =
            $limit =
            $token = null;
        } else {
            $session = $request->getSession();

            $ip = $request->query->get('ip', $session->get('_profiler_search_ip'));
            $method = $request->query->get('method', $session->get('_profiler_search_method'));
            $statusCode = $request->query->get('status_code', $session->get('_profiler_search_status_code'));
            $url = $request->query->get('url', $session->get('_profiler_search_url'));
            $start = $request->query->get('start', $session->get('_profiler_search_start'));
            $end = $request->query->get('end', $session->get('_profiler_search_end'));
            $limit = $request->query->get('limit', $session->get('_profiler_search_limit'));
            $token = $request->query->get('token', $session->get('_profiler_search_token'));
        }

        return new Response(
            $this->twig->render('@WebProfiler/Profiler/search.html.twig', [
                'token' => $token,
                'ip' => $ip,
                'method' => $method,
                'status_code' => $statusCode,
                'url' => $url,
                'start' => $start,
                'end' => $end,
                'limit' => $limit,
                'request' => $request,
            ]),
            200,
            ['Content-Type' => 'text/html']
        );
    }

    /**
     * Renders the search results.
     *
     * @throws NotFoundHttpException
     */
    public function searchResultsAction(Request $request, string $token): Response
    {
        $this->denyAccessIfProfilerDisabled();

        $this->cspHandler?->disableCsp();

        $profile = $this->profiler->loadProfile($token);

        $ip = $request->query->get('ip');
        $method = $request->query->get('method');
        $statusCode = $request->query->get('status_code');
        $url = $request->query->get('url');
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');

        return $this->renderWithCspNonces($request, '@WebProfiler/Profiler/results.html.twig', [
            'request' => $request,
            'token' => $token,
            'profile' => $profile,
            'tokens' => $this->profiler->find($ip, $url, $limit, $method, $start, $end, $statusCode),
            'ip' => $ip,
            'method' => $method,
            'status_code' => $statusCode,
            'url' => $url,
            'start' => $start,
            'end' => $end,
            'limit' => $limit,
            'panel' => null,
        ]);
    }

    /**
     * Narrows the search bar.
     *
     * @throws NotFoundHttpException
     */
    public function searchAction(Request $request): Response
    {
        $this->denyAccessIfProfilerDisabled();

        $ip = $request->query->get('ip');
        $method = $request->query->get('method');
        $statusCode = $request->query->get('status_code');
        $url = $request->query->get('url');
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');
        $token = $request->query->get('token');

        if ($request->hasSession()) {
            $session = $request->getSession();

            $session->set('_profiler_search_ip', $ip);
            $session->set('_profiler_search_method', $method);
            $session->set('_profiler_search_status_code', $statusCode);
            $session->set('_profiler_search_url', $url);
            $session->set('_profiler_search_start', $start);
            $session->set('_profiler_search_end', $end);
            $session->set('_profiler_search_limit', $limit);
            $session->set('_profiler_search_token', $token);
        }

        if (!empty($token)) {
            return new RedirectResponse($this->generator->generate('_profiler', ['token' => $token]), 302, ['Content-Type' => 'text/html']);
        }

        $tokens = $this->profiler->find($ip, $url, $limit, $method, $start, $end, $statusCode);

        return new RedirectResponse($this->generator->generate('_profiler_search_results', [
            'token' => $tokens ? $tokens[0]['token'] : 'empty',
            'ip' => $ip,
            'method' => $method,
            'status_code' => $statusCode,
            'url' => $url,
            'start' => $start,
            'end' => $end,
            'limit' => $limit,
        ]), 302, ['Content-Type' => 'text/html']);
    }

    /**
     * Displays the PHP info.
     *
     * @throws NotFoundHttpException
     */
    public function phpinfoAction(): Response
    {
        $this->denyAccessIfProfilerDisabled();

        $this->cspHandler?->disableCsp();

        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();

        return new Response($phpinfo, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Displays the Xdebug info.
     *
     * @throws NotFoundHttpException
     */
    public function xdebugAction(): Response
    {
        $this->denyAccessIfProfilerDisabled();

        if (!\function_exists('xdebug_info')) {
            throw new NotFoundHttpException('Xdebug must be installed in version 3.');
        }

        $this->cspHandler?->disableCsp();

        ob_start();
        xdebug_info();
        $xdebugInfo = ob_get_clean();

        return new Response($xdebugInfo, 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Displays the source of a file.
     *
     * @throws NotFoundHttpException
     */
    public function openAction(Request $request): Response
    {
        if (null === $this->baseDir) {
            throw new NotFoundHttpException('The base dir should be set.');
        }

        $this->profiler?->disable();

        $file = $request->query->get('file');
        $line = $request->query->get('line');

        $filename = $this->baseDir.\DIRECTORY_SEPARATOR.$file;

        if (preg_match("'(^|[/\\\\])\.'", $file) || !is_readable($filename)) {
            throw new NotFoundHttpException(sprintf('The file "%s" cannot be opened.', $file));
        }

        return $this->renderWithCspNonces($request, '@WebProfiler/Profiler/open.html.twig', [
            'filename' => $filename,
            'file' => $file,
            'line' => $line,
        ]);
    }

    protected function getTemplateManager(): TemplateManager
    {
        if (null === $this->templateManager) {
            $this->templateManager = new TemplateManager($this->profiler, $this->twig, $this->templates);
        }

        return $this->templateManager;
    }

    private function denyAccessIfProfilerDisabled()
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();
    }

    private function renderWithCspNonces(Request $request, string $template, array $variables, int $code = 200, array $headers = ['Content-Type' => 'text/html']): Response
    {
        $response = new Response('', $code, $headers);

        $nonces = $this->cspHandler ? $this->cspHandler->getNonces($request, $response) : [];

        $variables['csp_script_nonce'] = $nonces['csp_script_nonce'] ?? null;
        $variables['csp_style_nonce'] = $nonces['csp_style_nonce'] ?? null;

        $response->setContent($this->twig->render($template, $variables));

        return $response;
    }
}
