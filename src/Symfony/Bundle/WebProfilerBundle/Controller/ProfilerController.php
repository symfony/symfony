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

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Session\Flash\AutoExpireFlashBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\WebProfilerBundle\Profiler\TemplateManager;

/**
 * ProfilerController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerController extends ContainerAware
{
    protected $templateManager;

    /**
     * Renders a profiler panel for the given token.
     *
     * @param Request $request The HTTP request
     * @param string  $token   The profiler token
     *
     * @return Response A Response instance
     */
    public function panelAction(Request $request, $token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $panel = $request->query->get('panel', 'request');
        $page = $request->query->get('page', 'home');

        if (!$profile = $profiler->loadProfile($token)) {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:info.html.twig', array('about' => 'no_token', 'token' => $token));
        }

        if (!$profile->hasCollector($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not available for token "%s".', $panel, $token));
        }

        return $this->container->get('templating')->renderResponse($this->getTemplateManager()->getName($profile, $panel), array(
            'token'     => $token,
            'profile'   => $profile,
            'collector' => $profile->getCollector($panel),
            'panel'     => $panel,
            'page'      => $page,
            'templates' => $this->getTemplateManager()->getTemplates($profile),
            'is_ajax'   => $request->isXmlHttpRequest(),
        ));
    }

    /**
     * Exports data for a given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     */
    public function exportAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        if (!$profile = $profiler->loadProfile($token)) {
            throw new NotFoundHttpException(sprintf('Token "%s" does not exist.', $token));
        }

        return new Response($profiler->export($profile), 200, array(
            'Content-Type'        => 'text/plain',
            'Content-Disposition' => 'attachment; filename= '.$token.'.txt',
        ));
    }

    /**
     * Purges all tokens.
     *
     * @return Response A Response instance
     */
    public function purgeAction()
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();
        $profiler->purge();

        return new RedirectResponse($this->container->get('router')->generate('_profiler_info', array('about' => 'purge')));
    }

    /**
     * Imports token data.
     *
     * @param Request $request
     *
     * @return Response A Response instance
     */
    public function importAction(Request $request)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $router = $this->container->get('router');

        $file = $request->files->get('file');

        if (empty($file) || !$file->isValid()) {
            return new RedirectResponse($router->generate('_profiler_info', array('about' => 'upload_error')));
        }

        if (!$profile = $profiler->import(file_get_contents($file->getPathname()))) {
            return new RedirectResponse($router->generate('_profiler_info', array('about' => 'already_exists')));
        }

        return new RedirectResponse($router->generate('_profiler', array('token' => $profile->getToken())));
    }

    /**
     * Displays information page.
     *
     * @param string $about
     *
     * @return Response A Response instance
     */
    public function infoAction($about)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:info.html.twig', array(
            'about' => $about
        ));
    }

    /**
     * Renders the Web Debug Toolbar.
     *
     * @param Request $request  The current Request
     * @param string  $token    The profiler token
     * @param string  $position The toolbar position (top, bottom, normal, or null -- use the configuration)
     *
     * @return Response A Response instance
     */
    public function toolbarAction(Request $request, $token, $position = null)
    {
        $session = $request->getSession();

        if (null !== $session && $session->getFlashBag() instanceof AutoExpireFlashBag) {
            // keep current flashes for one more request if using AutoExpireFlashBag
            $session->getFlashBag()->setAll($session->getFlashBag()->peekAll());
        }

        if (null === $token) {
            return new Response();
        }

        $profiler = $this->container->get('profiler');
        $profiler->disable();

        if (!$profile = $profiler->loadProfile($token)) {
            return new Response();
        }

        if (null === $position) {
            $position = $this->container->getParameter('web_profiler.debug_toolbar.position');
        }

        $url = null;
        try {
            $url = $this->container->get('router')->generate('_profiler', array('token' => $token));
        } catch (\Exception $e) {
            // the profiler is not enabled
        }

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:toolbar.html.twig', array(
            'position'     => $position,
            'profile'      => $profile,
            'templates'    => $this->getTemplateManager()->getTemplates($profile),
            'profiler_url' => $url,
        ));
    }

    /**
     * Renders the profiler search bar.
     *
     * @param Request $request  The current Request
     *
     * @return Response A Response instance
     */
    public function searchBarAction(Request $request)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        if (null === $session = $request->getSession()) {
            $ip     =
            $method =
            $url    =
            $limit  =
            $token  = null;
        } else {
            $ip     = $session->get('_profiler_search_ip');
            $method = $session->get('_profiler_search_method');
            $url    = $session->get('_profiler_search_url');
            $limit  = $session->get('_profiler_search_limit');
            $token  = $session->get('_profiler_search_token');
        }

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:search.html.twig', array(
            'token'  => $token,
            'ip'     => $ip,
            'method' => $method,
            'url'    => $url,
            'limit'  => $limit,
        ));
    }

    /**
     * Search results.
     *
     * @param Request $request  The current Request
     * @param string  $token    The token
     *
     * @return Response A Response instance
     */
    public function searchResultsAction(Request $request, $token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $profile = $profiler->loadProfile($token);

        $ip     = $request->query->get('ip');
        $method = $request->query->get('method');
        $url    = $request->query->get('url');
        $limit  = $request->query->get('limit');

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:results.html.twig', array(
            'token'    => $token,
            'profile'  => $profile,
            'tokens'   => $profiler->find($ip, $url, $limit, $method),
            'ip'       => $ip,
            'method'   => $method,
            'url'      => $url,
            'limit'    => $limit,
            'panel'    => null,
        ));
    }

    /**
     * Narrow the search bar.
     *
     * @param Request $request  The current Request
     *
     * @return Response A Response instance
     */
    public function searchAction(Request $request)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $ip     = preg_replace('/[^:\d\.]/', '', $request->query->get('ip'));
        $method = $request->query->get('method');
        $url    = $request->query->get('url');
        $limit  = $request->query->get('limit');
        $token  = $request->query->get('token');

        if (null !== $session = $request->getSession()) {
            $session->set('_profiler_search_ip', $ip);
            $session->set('_profiler_search_method', $method);
            $session->set('_profiler_search_url', $url);
            $session->set('_profiler_search_limit', $limit);
            $session->set('_profiler_search_token', $token);
        }

        if (!empty($token)) {
            return new RedirectResponse($this->container->get('router')->generate('_profiler', array('token' => $token)));
        }

        $tokens = $profiler->find($ip, $url, $limit, $method);

        return new RedirectResponse($this->container->get('router')->generate('_profiler_search_results', array(
            'token'  => $tokens ? $tokens[0]['token'] : 'empty',
            'ip'     => $ip,
            'method' => $method,
            'url'    => $url,
            'limit'  => $limit,
        )));
    }

    /**
     * Displays the PHP info.
     *
     * @return Response A Response instance
     */
    public function phpinfoAction()
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        ob_start();
        phpinfo();
        $phpinfo = ob_get_clean();

        return new Response($phpinfo);
    }

    protected function getTemplateManager()
    {
        if (null === $this->templateManager) {
            $this->templateManager = new TemplateManager(
                $this->container->get('profiler'),
                $this->container->get('templating'),
                $this->container->get('twig'),
                $this->container->getParameter('data_collector.templates')
            );
        }

        return $this->templateManager;
    }
}
