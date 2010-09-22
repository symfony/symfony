<?php

namespace Symfony\Bundle\WebProfilerBundle\Controller;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\OutputEscaper\SafeDecorator;

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
     * Renders the main profiler page for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     */
    public function indexAction($token)
    {
        $this->container->get('profiler')->disable();

        $profiler = $this->container->get('profiler')->loadFromToken($token);

        if ($profiler->isEmpty()) {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:notfound', array(
                'token'     => $token,
            ));
        } else {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:index', array(
                'token'     => $token,
                'profiler'  => new SafeDecorator($profiler),
                'collector' => $profiler->get('request'),
                'template'  => $this->getTemplate($profiler, '_panel', 'request'),
                'panel'     => 'request',
            ));
        }
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

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:toolbar', array(
            'position'  => $position,
            'profiler'  => new SafeDecorator($profiler),
            'templates' => $this->getTemplates($profiler, '_bar'),
        ));
    }

    /**
     * Renders a profiler panel for the given token.
     *
     * @param string $token The profiler token
     *
     * @return Response A Response instance
     */
    public function panelAction($token, $panel)
    {
        $this->container->get('profiler')->disable();

        $profiler = $this->container->get('profiler')->loadFromToken($token);
        if (!$profiler->has($panel)) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not registered.', $panel));
        }

        if ($profiler->isEmpty()) {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:notfound', array(
                'token'     => $token,
            ));
        } else {
            return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:panel', array(
                'token'     => $token,
                'profiler'  => new SafeDecorator($profiler),
                'collector' => new SafeDecorator($profiler->get($panel)),
                'template'  => $this->getTemplate($profiler, '_panel', $panel),
                'panel'     => $panel,
            ));
        }
    }

    /**
     * Renders the profiler menu for the given token.
     *
     * @param string $token The profiler token
     * @param string $panel The current panel
     *
     * @return Response A Response instance
     */
    public function listAction($token, $panel)
    {
        $profiler = $this->container->get('profiler')->loadFromToken($token);

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:menu', array(
            'token'     => $token,
            'profiler'  => new SafeDecorator($profiler),
            'templates' => $this->getTemplates($profiler, '_menu'),
            'panel'     => $panel,
        ));
    }

    /**
     * Renders the profiler search bar.
     *
     * @return Response A Response instance
     */
    public function menuAction($token)
    {
        $profiler = $this->container->get('profiler');
        $profiler->disable();

        $session = $this->container->get('request')->getSession();
        $ip = $session->get('_profiler_search_ip');
        $url = $session->get('_profiler_search_url');
        $limit = $session->get('_profiler_search_limit');

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:search', array(
            'token'    => $token,
            'profiler' => new SafeDecorator($profiler),
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

        return $this->container->get('templating')->renderResponse('WebProfilerBundle:Profiler:results', array(
            'token'    => $token,
            'profiler' => new SafeDecorator($this->container->get('profiler')->loadFromToken($token)),
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

    protected function getTemplates($profiler, $suffix)
    {
        $templates = array();
        foreach ($this->container->getParameter('data_collector.templates') as $name => $template) {
            if ($profiler->has($name)) {
                if (!$this->container->get('templating')->exists($template.$suffix)) {
                    continue;
                }

                $templates[$name] = $template.$suffix;
            }
        }

        return $templates;
    }

    protected function getTemplate($profiler, $suffix, $panel)
    {
        $templates = $this->getTemplates($profiler, $suffix);

        if (!isset($templates[$panel])) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not registered.', $panel));
        }

        return $templates[$panel];
    }
}
