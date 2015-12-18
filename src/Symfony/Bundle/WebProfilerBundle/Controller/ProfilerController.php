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

use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Profiler\Profile;
use Symfony\Component\Profiler\Profiler;
use Symfony\Component\Profiler\Storage\ProfilerStorageInterface;
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
    private $profilerStorage;
    private $twig;
    private $templates;
    private $toolbarPosition;

    /**
     * Constructor.
     *
     * @param UrlGeneratorInterface     $generator          The URL Generator
     * @param Profiler                  $profiler           The profiler
     * @param ProfilerStorageInterface  $profilerStorage    The profiler storage
     * @param \Twig_Environment         $twig               The twig environment
     * @param array                     $templates          The templates
     * @param string                    $toolbarPosition    The toolbar position (top, bottom, normal, or null -- use the configuration)
     */
    public function __construct(UrlGeneratorInterface $generator, Profiler $profiler = null, ProfilerStorageInterface $profilerStorage = null,
                                \Twig_Environment $twig, array $templates, $toolbarPosition = 'normal')
    {
        $this->generator = $generator;
        $this->profiler = $profiler;
        $this->profilerStorage = $profilerStorage;
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
        if (null === $this->profiler || null === $this->profilerStorage) {
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
        if (null === $this->profiler || null === $this->profilerStorage) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $panel = $request->query->get('panel', 'request');
        $page = $request->query->get('page', 'home');

        if ('latest' === $token && $latest = current($this->profilerStorage->findBy(array(), 1, null, null))) {
            $token = $latest['token'];
        }

        if (!$profile = $this->profilerStorage->read($token)) {
            return new Response($this->twig->render('@WebProfiler/Profiler/info.html.twig', array('about' => 'no_token', 'token' => $token)), 200, array('Content-Type' => 'text/html'));
        }

        $panel = $request->query->get('panel', $profile instanceof Profile?'request':'config');

        if (!$profile->has($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not available for token "%s".', $panel, $token));
        }

        return new Response($this->twig->render($this->getTemplateManager()->getName($profile, $panel), array(
            'token' => $token,
            'profile' => $profile,
            'collector' => $profile->get($panel),
            'panel' => $panel,
            'page' => $page,
            'request' => $request,
            'templates' => $this->getTemplateManager()->getTemplates($profile),
            'is_ajax' => $request->isXmlHttpRequest(),
            'profiler_markup_version' => 2, // 1 = original profiler, 2 = Symfony 2.8+ profiler
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
        @trigger_error('The '.__METHOD__.' method is deprecated since version 2.8 and will be removed in 3.0.', E_USER_DEPRECATED);

        if (null === $this->profiler || null === $this->profilerStorage) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();
        $this->profilerStorage->purge();

        return new RedirectResponse($this->generator->generate('_profiler_info', array('about' => 'purge')), 302, array('Content-Type' => 'text/html'));
    }

    /**
     * Displays information page.
     *
     * @param Request $request The current HTTP Request
     * @param string  $about   The about message
     *
     * @return Response A Response instance
     *
     * @throws NotFoundHttpException
     */
    public function infoAction(Request $request, $about)
    {
        if (null === $this->profiler || null === $this->profilerStorage) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        return new Response($this->twig->render('@WebProfiler/Profiler/info.html.twig', array(
            'about' => $about,
            'request' => $request,
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
        if (null === $this->profiler || null === $this->profilerStorage) {
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

        if (!$profile = $this->profilerStorage->read($token)) {
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
            'request' => $request,
            'position' => $position,
            'profile' => $profile,
            'templates' => $this->getTemplateManager()->getTemplates($profile),
            'profiler_url' => $url,
            'token' => $token,
            'profiler_markup_version' => 2, // 1 = original toolbar, 2 = Symfony 2.8+ toolbar
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
        if (null === $this->profiler || null === $this->profilerStorage) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        if (null === $session = $request->getSession()) {
            $filters = array();
            $start =
            $end =
            $limit = null;
        } else {
            $filters = $request->query->get('filters', $session->get('_profiler_search_filters'));
            $start = $request->query->get('limit', $session->get('_profiler_search_limit'));
            $end = $request->query->get('limit', $session->get('_profiler_search_limit'));
            $limit = $request->query->get('limit', $session->get('_profiler_search_limit'));
        }

        return new Response(
            $this->twig->render('@WebProfiler/Profiler/search.html.twig', array(
                'filters' => $filters,
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
        if (null === $this->profiler || null === $this->profilerStorage) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $profile = $this->profilerStorage->read($token);

        $filters = $request->query->get('filters', array());
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');

        return new Response($this->twig->render('@WebProfiler/Profiler/results.html.twig', array(
            'request' => $request,
            'token' => $token,
            'profile' => $profile,
            'tokens' => $this->profilerStorage->findBy(array_replace($filters, array('profile_type' => 'http')), $limit, $start, $end),
            'filters' => $filters,
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
        if (null === $this->profiler || null === $this->profilerStorage) {
            throw new NotFoundHttpException('The profiler must be enabled.');
        }

        $this->profiler->disable();

        $filters = $request->query->get('filters', array());
        if ( isset($filters['id']) ) {
            $filters['ip'] = preg_replace('/[^:\d\.]/', '', $filters['ip']);
        }
        $start = $request->query->get('start', null);
        $end = $request->query->get('end', null);
        $limit = $request->query->get('limit');

        if (null !== $session = $request->getSession()) {
            $session->set('_profiler_search_filters', $filters);
            $session->set('_profiler_search_start', $start);
            $session->set('_profiler_search_end', $end);
            $session->set('_profiler_search_limit', $limit);
        }

        if (!empty($filters['token'])) {
            return new RedirectResponse($this->generator->generate('_profiler', array('token' => $filters['token'])), 302, array('Content-Type' => 'text/html'));
        }

        $tokens = $this->profilerStorage->findBy(array_replace($filters, array('profile_type' => 'http')), $limit, $start, $end);

        return new RedirectResponse($this->generator->generate('_profiler_search_results', array(
            'request' => $request,
            'token' => $tokens ? $tokens[0]['token'] : 'empty',
            'filters' => $filters,
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
        if (null === $this->profiler || null === $this->profilerStorage) {
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
            $this->templateManager = new TemplateManager($this->twig, $this->templates);
        }

        return $this->templateManager;
    }
}
