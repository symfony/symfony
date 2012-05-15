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

/**
 * ProfilerController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ProfilerController extends ContainerAware
{
    /**
     * Renders a profiler panel for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     */
    public function panelAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $panel = $this->container->get('request')->query->get('panel', 'request');

        if (!$profile = $profiler->loadProfile($token)) {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:notfound.html.twig', array('token' => $token));
        }

        if (!$profile->hasCollector($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not available for token "%s".', $panel, $token));
        }

        return $this->container->get('templating')->renderResponse($this->getTemplateName($profiler, $panel), array(
            'token'     => $token,
            'profile'   => $profile,
            'collector' => $profile->getCollector($panel),
            'panel'     => $panel,
            'templates' => $this->getTemplates($profiler),
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

        return new RedirectResponse($this->container->get('router')->generate('_profiler', array('token' => '-')));
    }

    /**
     * Imports token data.
     *
     * @return Response A Response instance
     */
    public function importAction()
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $file = $this->container->get('request')->files->get('file');
        if (empty($file) || UPLOAD_ERR_OK !== $file->getError()) {
            throw new \RuntimeException('Problem uploading the data.');
        }

        if (!$profile = $profiler->import(file_get_contents($file->getPathname()))) {
            throw new \RuntimeException('Problem uploading the data (token already exists).');
        }

        return new RedirectResponse($this->container->get('router')->generate('_profiler', array('token' => $profile->getToken())));
    }

    /**
     * Renders the Web Debug Toolbar.
     *
     * @param string $token    The profiler token
     * @param string $position The toolbar position (bottom, normal, or null -- automatically guessed)
     *
     * @return Response A Response instance
     */
    public function toolbarAction($token, $position = null)
    {
        $request = $this->container->get('request');

        if (null !== $session = $request->getSession()) {
            // keep current flashes for one more request
            $session->setFlashes($session->getFlashes());
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
            $position = false === strpos($this->container->get('request')->headers->get('user-agent'), 'Mobile') ? 'fixed' : 'absolute';
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
            'templates'    => $this->getTemplates($profiler),
            'profiler_url' => $url,
            'verbose'      => $this->container->get('web_profiler.debug_toolbar')->isVerbose()
        ));
    }

    /**
     * Renders the profiler search bar.
     *
     * @return Response A Response instance
     */
    public function searchBarAction()
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        if (null === $session = $this->container->get('request')->getSession()) {
            $ip    =
            $url   =
            $limit =
            $token = null;
        } else {
            $ip    = $session->get('_profiler_search_ip');
            $url   = $session->get('_profiler_search_url');
            $limit = $session->get('_profiler_search_limit');
            $token = $session->get('_profiler_search_token');
        }

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:search.html.twig', array(
            'token' => $token,
            'ip'    => $ip,
            'url'   => $url,
            'limit' => $limit,
        ));
    }

    /**
     * Search results.
     *
     * @param string $token The token
     *
     * @return Response A Response instance
     */
    public function searchResultsAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $profile = $profiler->loadProfile($token);

        $ip    = $this->container->get('request')->query->get('ip');
        $url   = $this->container->get('request')->query->get('url');
        $limit = $this->container->get('request')->query->get('limit');

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:results.html.twig', array(
            'token'    => $token,
            'profile'  => $profile,
            'tokens'   => $profiler->find($ip, $url, $limit),
            'ip'       => $ip,
            'url'      => $url,
            'limit'    => $limit,
            'panel'    => null,
        ));
    }

    /**
     * Narrow the search bar.
     *
     * @return Response A Response instance
     */
    public function searchAction()
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $request = $this->container->get('request');

        $ip    = preg_replace('/[^:\d\.]/', '', $request->query->get('ip'));
        $url   = $request->query->get('url');
        $limit = $request->query->get('limit');
        $token = $request->query->get('token');

        if (null !== $session = $request->getSession()) {
            $session->set('_profiler_search_ip', $ip);
            $session->set('_profiler_search_url', $url);
            $session->set('_profiler_search_limit', $limit);
            $session->set('_profiler_search_token', $token);
        }

        if (!empty($token)) {
            return new RedirectResponse($this->container->get('router')->generate('_profiler', array('token' => $token)));
        }

        $tokens = $profiler->find($ip, $url, $limit);

        return new RedirectResponse($this->container->get('router')->generate('_profiler_search_results', array(
            'token' => $tokens ? $tokens[0]['token'] : 'empty',
            'ip'    => $ip,
            'url'   => $url,
            'limit' => $limit,
        )));
    }

    protected function getTemplateNames($profiler)
    {
        $templates = array();
        foreach ($this->container->getParameter('data_collector.templates') as $id => $arguments) {
            if (null === $arguments) {
                continue;
            }

            list($name, $template) = $arguments;
            if (!$profiler->has($name)) {
                continue;
            }

            if ('.html.twig' === substr($template, -10)) {
                $template = substr($template, 0, -10);
            }

            if (!$this->container->get('templating')->exists($template.'.html.twig')) {
                throw new \UnexpectedValueException(sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
            }

            $templates[$name] = $template.'.html.twig';
        }

        return $templates;
    }

    protected function getTemplateName($profiler, $panel)
    {
        $templates = $this->getTemplateNames($profiler);

        if (!isset($templates[$panel])) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not registered.', $panel));
        }

        return $templates[$panel];
    }

    protected function getTemplates($profiler)
    {
        $templates = $this->getTemplateNames($profiler);
        foreach ($templates as $name => $template) {
            $templates[$name] = $this->container->get('twig')->loadTemplate($template);
        }

        return $templates;
    }
}
