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

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * ProfilerController.
 *
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

    /**
     * Constructor.
     *
     * @param UrlGeneratorInterface $generator       The URL Generator
     * @param Profiler              $profiler        The profiler
     * @param \Twig_Environment     $twig            The twig environment
     * @param array                 $templates       The templates
     * @param string                $toolbarPosition The toolbar position (top, bottom, normal, or null -- use the configuration)
     */
    public function __construct(UrlGeneratorInterface $generator, Profiler $profiler = null, \Twig_Environment $twig, array $templates, $toolbarPosition = 'normal')
    {
        $this->generator = $generator;
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->templates = $templates;
        $this->toolbarPosition = $toolbarPosition;
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

        $panel = $request->query->get('panel', 'request');
        $page = $request->query->get('page', 'home');

        if (!$profile = $this->profiler->loadProfile($token)) {
            return new Response($this->twig->render('@WebProfiler/Profiler/info.html.twig', array('about' => 'no_token', 'token' => $token)), 200, array('Content-Type' => 'text/html'));
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
            'templates' => $this->getTemplateManager()->getTemplates($profile),
            'is_ajax' => $request->isXmlHttpRequest(),
        )), 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Purges all tokens.
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function purgeAction()
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();
        $this->profiler->purge();

        return new RedirectResponse($this->generator->generate('_profiler_info', array('about' => 'purge')), 302, array('Content-Type' => 'text/html'));
    }

    /**
     * Displays information page.
     *
     * @param string $about The about message
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function infoAction($about)
    {
        if (null === $this->profiler) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        return new Response($this->twig->render('@WebProfiler/Profiler/info.html.twig', array(
            'about' => $about,
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

        return new Response($this->twig->render('@WebProfiler/Profiler/toolbar.html.twig', array(
            'position' => $position,
            'profile' => $profile,
            'templates' => $this->getTemplateManager()->getTemplates($profile),
            'profiler_url' => $url,
            'token' => $token,
        )), 200, array('Content-Type' => 'text/html'));
    }

    /**
     * Renders the profiler search bar.
     *
     * @param Request $request The current HTTP Request
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

        if (null === $session = $request->getSession()) {
            $ip =
            $method =
            $url =
            $start =
            $end =
            $limit =
            $token = null;
        } else {
            $ip = $session->get('_profiler_search_ip');
            $method = $session->get('_profiler_search_method');
            $url = $session->get('_profiler_search_url');
            $start = $session->get('_profiler_search_start');
            $end = $session->get('_profiler_search_end');
            $limit = $session->get('_profiler_search_limit');
            $token = $session->get('_profiler_search_token');
        }

        return new Response($this->twig->render('@WebProfiler/Profiler/search.html.twig', array(
            'token' => $token,
            'ip' => $ip,
            'method' => $method,
            'url' => $url,
            'start' => $start,
            'end' => $end,
            'limit' => $limit,
            'request' => $request,
        )), 200, array('Content-Type' => 'text/html'));
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

        $profile = $this->profiler->loadProfile($token);

        $ip = $request->query->get('ip');
        $method = $request->query->get('method');
        $url = $request->query->get('url');
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');

        return new Response($this->twig->render('@WebProfiler/Profiler/results.html.twig', array(
            'token' => $token,
            'profile' => $profile,
            'tokens' => $this->profiler->find($ip, $url, $limit, $method, $start, $end),
            'ip' => $ip,
            'method' => $method,
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
     * @param Request $request The current HTTP Request
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
        $url = $request->query->get('url');
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');
        $token = $request->query->get('token');

        if (null !== $session = $request->getSession()) {
            $session->set('_profiler_search_ip', $ip);
            $session->set('_profiler_search_method', $method);
            $session->set('_profiler_search_url', $url);
            $session->set('_profiler_search_start', $start);
            $session->set('_profiler_search_end', $end);
            $session->set('_profiler_search_limit', $limit);
            $session->set('_profiler_search_token', $token);
        }

        if (!empty($token)) {
            return new RedirectResponse($this->generator->generate('_profiler', array('token' => $token)), 302, array('Content-Type' => 'text/html'));
        }

        $tokens = $this->profiler->find($ip, $url, $limit, $method, $start, $end);

        return new RedirectResponse($this->generator->generate('_profiler_search_results', array(
            'token' => $tokens ? $tokens[0]['token'] : 'empty',
            'ip' => $ip,
            'method' => $method,
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

        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();

        return new Response($phpinfo, 200, array('Content-Type' => 'text/html'));
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
}
