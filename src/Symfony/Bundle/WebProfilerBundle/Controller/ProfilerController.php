<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ProfilerController.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
    public function panelAction($token, $panel = 'request')
    {
        $this->container->get('profiler')->disable();

        $profiler = $this->container->get('profiler')->loadFromToken($token);

        if ($profiler->isEmpty()) {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:notfound.html.twig', array('token' => $token));
        }

        if (!$profiler->has($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not registered.', $panel));
        }

        return $this->container->get('templating')->renderResponse($this->getTemplateName($profiler, $panel), array(
            'token'     => $token,
            'profiler'  => $profiler,
            'collector' => $profiler->get($panel),
            'panel'     => $panel,
            'templates' => $this->getTemplates($profiler),
        ));
    }

    /**
     * Exports data for a given token.
     *
     * @param string $token    The profiler token
     *
     * @return Response A Response instance
     */
    public function exportAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $profiler = $profiler->loadFromToken($token);

        if ($profiler->isEmpty()) {
            throw new NotFoundHttpException(sprintf('Token "%s" does not exist.', $token));
        }

        $response = $this->container->get('response');
        $response->setContent($profiler->export());
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename= '.$token.'.txt');

        return $response;
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

        $response = $this->container->get('response');
        $response->setRedirect($this->container->get('router')->generate('_profiler', array('token' => '-')));

        return $response;
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
        if (!$file || UPLOAD_ERR_OK !== $file->getError()) {
            throw new \RuntimeException('Problem uploading the data.');
        }

        $token = $profiler->import(file_get_contents($file->getPath()));

        if (false === $token) {
            throw new \RuntimeException('Problem uploading the data (token already exists).');
        }

        $response = $this->container->get('response');
        $response->setRedirect($this->container->get('router')->generate('_profiler', array('token' => $token)));

        return $response;
    }

    /**
     * Renders the Web Debug Toolbar.
     *
     * @param string $token    The profiler token
     * @param string $position The toolbar position (bottom, normal, or null -- automatically guessed)
     *
     * @return Response A Response instance
     */
    public function toolbarAction($token = null, $position = null)
    {
        $profiler = $this->container->get('profiler');

        if (null !== $token) {
            $profiler = $profiler->loadFromToken($token);

            if ($profiler->isEmpty()) {
                return $this->container->get('response');
            }
        }

        if (null === $position) {
            $position = false === strpos($this->container->get('request')->headers->get('user-agent'), 'Mobile') ? 'fixed' : 'absolute';
        }

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:toolbar.html.twig', array(
            'position'  => $position,
            'profiler'  => $profiler,
            'templates' => $this->getTemplates($profiler),
        ));
    }

    /**
     * Renders the profiler search bar.
     *
     * @return Response A Response instance
     */
    public function searchBarAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $session = $this->container->get('request')->getSession();
        $ip = $session->get('_profiler_search_ip');
        $url = $session->get('_profiler_search_url');
        $limit = $session->get('_profiler_search_limit');

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:search.html.twig', array(
            'token'    => $token,
            'profiler' => $profiler,
            'tokens'   => $profiler->find($ip, $url, $limit),
            'ip'       => $ip,
            'url'      => $url,
            'limit'    => $limit,
        ));
    }

    /**
     * Search results.
     *
     * @return Response A Response instance
     */
    public function searchResultsAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $session = $this->container->get('request')->getSession();
        $ip = $session->get('_profiler_search_ip');
        $url = $session->get('_profiler_search_url');
        $limit = $session->get('_profiler_search_limit');

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:results.html.twig', array(
            'token'    => $token,
            'profiler' => $this->container->get('profiler')->loadFromToken($token),
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

        if ($token = $request->query->get('token')) {
            $response = $this->container->get('response');
            $response->setRedirect($this->container->get('router')->generate('_profiler', array('token' => $token)));

            return $response;
        }

        $session = $request->getSession();
        $session->set('_profiler_search_ip', $ip = preg_replace('/[^\d\.]/', '', $request->query->get('ip')));
        $session->set('_profiler_search_url', $url = $request->query->get('url'));
        $session->set('_profiler_search_limit', $limit = $request->query->get('limit'));

        $profiler = $this->container->get('profiler');
        $profiler->disable();
        $tokens = $profiler->find($ip, $url, $limit);

        $response = $this->container->get('response');
        $response->setRedirect($this->container->get('router')->generate('_profiler_search_results', array('token' => $tokens ? $tokens[0]['token'] : '')));

        return $response;
    }

    protected function getTemplateNames($profiler)
    {
        $templates = array();
        foreach ($this->container->getParameter('data_collector.templates') as $id => $arguments) {
            if (null === $arguments) {
                continue;
            }

            list($name, $template) = $arguments;
            if (!$profiler->has($name) || !$this->container->get('templating')->exists($template.'.html.twig')) {
                continue;
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
