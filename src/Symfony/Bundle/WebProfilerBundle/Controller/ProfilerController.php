<?php

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:notfound.twig', array('token' => $token));
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
        if (!$file || 0 !== $file['error']) {
            throw new \RuntimeException('Problem uploading the data.');
        }

        $token = $profiler->import(file_get_contents($file['tmp_name']));

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

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:toolbar.twig', array(
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

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:search.twig', array(
            'token'    => $token,
            'profiler' => $profiler,
            'tokens'   => $profiler->find($ip, $url, 10),
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

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:results.twig', array(
            'token'    => $token,
            'profiler' => $this->container->get('profiler')->loadFromToken($token),
            'tokens'   => $profiler->find($ip, $url, 10),
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
        $response->setRedirect($this->container->get('router')->generate('_profiler_search_results', array('token' => $tokens[0]['token'])));

        return $response;
    }

    protected function getTemplateNames($profiler)
    {
        $templates = array();
        foreach ($this->container->findTaggedServiceIds('data_collector') as $id => $tags) {
            if ($this->container->has($id) && isset($tags[0]['template'])) {
                $name = $this->container->get($id)->getName();
                $template = $tags[0]['template'];
                if ($profiler->has($name)) {
                    if (!$this->container->get('templating')->exists($template.'.twig')) {
                        continue;
                    }

                    $templates[$name] = $template.'.twig';
                }
            }
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
