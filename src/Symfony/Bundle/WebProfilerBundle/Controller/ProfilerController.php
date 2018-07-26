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

use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerController
{
    private $templateManager;
    private $generator;
    private $profiler;
    private $twig;
    private $templates;
    private $toolbarPosition;
    private $cspHandler;
    private $baseDir;

    /**
     * @param UrlGeneratorInterface        $generator       The URL Generator
     * @param Profiler                     $profiler        The profiler
     * @param Environment                  $twig            The twig environment
     * @param array                        $templates       The templates
     * @param string                       $toolbarPosition The toolbar position (top, bottom, normal, or null -- use the configuration)
     * @param ContentSecurityPolicyHandler $cspHandler      The Content-Security-Policy handler
     * @param string                       $baseDir         The project root directory
     */
    public function __construct(UrlGeneratorInterface $generator, Profiler $profiler = null, Environment $twig, array $templates, $toolbarPosition = 'bottom', ContentSecurityPolicyHandler $cspHandler = null, $baseDir = null)
    {
        $this->generator = $generator;
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->templates = $templates;
        $this->toolbarPosition = $toolbarPosition;
        $this->cspHandler = $cspHandler;
        $this->baseDir = $baseDir;
    }

    /**
     * Redirects to the last profiles.
     *
     * @return RedirectResponse A RedirectResponse instance
     *
     * @throws NotFoundHttpException
     */
    public function homeAction()
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        return new RedirectResponse($this->generator->generate('_profiler_search_results', array('token' => 'empty', 'limit' => 10)), 302, array('Content-Type' => 'text/html'));
    }

    /**
     * Renders a profiler panel for the given token.
     *
     * @param Request $request The current HTTP request
     * @param string  $token   The profiler token
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function panelAction(Request $request, $token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null !== $this->cspHandler) {
            $this->cspHandler->disableCsp();
        }

        $panel = $request->query->get('panel', 'request');
        $page = $request->query->get('page', 'home');

        if ('latest' === $token && $latest = current($this->profiler->find(null, null, 1, null, null, null))) {
            $token = $latest['token'];
        }

        if (!$profile = $this->profiler->loadProfile($token)) {
            return new Response($this->twig->render('@WebProfiler/Profiler/info.html.twig', array('about' => 'no_token', 'token' => $token, 'request' => $request)), 200, array('Content-Type' => 'text/html'));
        }

        if (!$profile->hasCollector($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not available for token "%s".', $panel, $token));
        }

        return new Response($this->twig->render($this->getTemplateManager()->getName($profile, $panel), array(
            'token' => $token,
            'profile' => $profile,
            'collector' => $profile->getCollector($panel),
            'panel' => $panel,
            'page' => $page,
            'request' => $request,
            'templates' => $this->getTemplateManager()->getNames($profile),
            'is_ajax' => $request->isXmlHttpRequest(),
            'profiler_markup_version' => 2, // 1 = original profiler, 2 = Symfony 2.8+ profiler
        )), 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Renders the Web Debug Toolbar.
     *
     * @param Request $request The current HTTP Request
     * @param string  $token   The profiler token
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function toolbarAction(Request $request, $token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $session = $request->getSession();

        if (null !== $session && $session->isStarted() && $session->getFlashBag() instanceof AutoExpireFlashBag) {
            // keep current flashes for one more request if using AutoExpireFlashBag
            $session->getFlashBag()->setAll($session->getFlashBag()->peekAll());
        }

        if ('empty' === $token || null === $token) {
            return new Response('', 200, array('Content-Type' => 'text/html'));
        }

        $this->profiler->disable();

        if (!$profile = $this->profiler->loadProfile($token)) {
            return new Response('', 404, array('Content-Type' => 'text/html'));
        }

        // the toolbar position (top, bottom, normal, or null -- use the configuration)
        if (null === $position = $request->query->get('position')) {
            $position = $this->toolbarPosition;
        }

        $url = null;
        try {
            $url = $this->generator->generate('_profiler', array('token' => $token));
        } catch (\Exception $e) {
            // the profiler is not enabled
        }

        return $this->renderWithCspNonces($request, '@WebProfiler/Profiler/toolbar.html.twig', array(
            'request' => $request,
            'position' => $position,
            'profile' => $profile,
            'templates' => $this->getTemplateManager()->getNames($profile),
            'profiler_url' => $url,
            'token' => $token,
            'profiler_markup_version' => 2, // 1 = original toolbar, 2 = Symfony 2.8+ toolbar
        ));
    }

    /**
     * Renders the profiler search bar.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function searchBarAction(Request $request)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null !== $this->cspHandler) {
            $this->cspHandler->disableCsp();
        }

        if (null === $session = $request->getSession()) {
            $ip =
            $method =
            $statusCode =
            $url =
            $start =
            $end =
            $limit =
            $token = null;
        } else {
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
            $this->twig->render('@WebProfiler/Profiler/search.html.twig', array(
                'token' => $token,
                'ip' => $ip,
                'method' => $method,
                'status_code' => $statusCode,
                'url' => $url,
                'start' => $start,
                'end' => $end,
                'limit' => $limit,
                'request' => $request,
            )),
            200,
            array('Content-Type' => 'text/html')
        );
    }

    /**
     * Renders the search results.
     *
     * @param Request $request The current HTTP Request
     * @param string  $token   The token
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function searchResultsAction(Request $request, $token)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null !== $this->cspHandler) {
            $this->cspHandler->disableCsp();
        }

        $profile = $this->profiler->loadProfile($token);

        $ip = $request->query->get('ip');
        $method = $request->query->get('method');
        $statusCode = $request->query->get('status_code');
        $url = $request->query->get('url');
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');

        return new Response($this->twig->render('@WebProfiler/Profiler/results.html.twig', array(
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
        )), 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Narrows the search bar.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function searchAction(Request $request)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $ip = preg_replace('/[^:\d\.]/', '', $request->query->get('ip'));
        $method = $request->query->get('method');
        $statusCode = $request->query->get('status_code');
        $url = $request->query->get('url');
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');
        $token = $request->query->get('token');

        if (null !== $session = $request->getSession()) {
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
            return new RedirectResponse($this->generator->generate('_profiler', array('token' => $token)), 302, array('Content-Type' => 'text/html'));
        }

        $tokens = $this->profiler->find($ip, $url, $limit, $method, $start, $end, $statusCode);

        return new RedirectResponse($this->generator->generate('_profiler_search_results', array(
            'token' => $tokens ? $tokens[0]['token'] : 'empty',
            'ip' => $ip,
            'method' => $method,
            'status_code' => $statusCode,
            'url' => $url,
            'start' => $start,
            'end' => $end,
            'limit' => $limit,
        )), 302, array('Content-Type' => 'text/html'));
    }

    /**
     * Displays the PHP info.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function phpinfoAction()
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null !== $this->cspHandler) {
            $this->cspHandler->disableCsp();
        }

        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();

        return new Response($phpinfo, 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Displays the source of a file.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function openAction(Request $request)
    {
        if (null === $this->baseDir) {
            throw new NotFoundHttpException('The base dir should be set.');
        }

        if ($this->profiler) {
            $this->profiler->disable();
        }

        $file = $request->query->get('file');
        $line = $request->query->get('line');

        $filename = $this->baseDir.\DIRECTORY_SEPARATOR.$file;

        if (preg_match("'(^|[/\\\\])\.'", $file) || !is_readable($filename)) {
            throw new NotFoundHttpException(sprintf('The file "%s" cannot be opened.', $file));
        }

        return new Response($this->twig->render('@WebProfiler/Profiler/open.html.twig', array(
            'filename' => $filename,
            'file' => $file,
            'line' => $line,
         )), 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Gets the Template Manager.
     *
     * @return TemplateManager The Template Manager
     */
    protected function getTemplateManager()
    {
        if (null === $this->templateManager) {
            $this->templateManager = new TemplateManager($this->profiler, $this->twig, $this->templates);
        }

        return $this->templateManager;
    }

    private function renderWithCspNonces(Request $request, $template, $variables, $code = 200, $headers = array('Content-Type' => 'text/html'))
    {
        $response = new Response('', $code, $headers);

        $nonces = $this->cspHandler ? $this->cspHandler->getNonces($request, $response) : array();

        $variables['csp_script_nonce'] = isset($nonces['csp_script_nonce']) ? $nonces['csp_script_nonce'] : null;
        $variables['csp_style_nonce'] = isset($nonces['csp_style_nonce']) ? $nonces['csp_style_nonce'] : null;

        $response->setContent($this->twig->render($template, $variables));

        return $response;
    }
}
