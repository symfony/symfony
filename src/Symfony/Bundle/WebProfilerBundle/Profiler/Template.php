<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Profiler;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Bundle\TwigBundle\TwigEngine;

/**
 * ProfilerController.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Artur Wielogórski <wodor@wodor.net>
 */
class Template {

    /**
     * @var \Symfony\Bundle\TwigBundle\TwigEngine
     */
    public $templating;

    /**
     * @var \Twig_Environment
     */
    public $twig;

    /**
     * @var array
     */
    public $templates;

    /**
     * @var \Symfony\Component\HttpKernel\Profiler\Profiler
     */
    protected $profiler;

    /**
     * @param \Symfony\Bundle\TwigBundle\TwigEngine $templating
     * @param \Twig_Environment $twig
     * @param array $templates
     */
    public function __construct(TwigEngine $templating, \Twig_Environment $twig, array $templates)
    {
        $this->templating = $templating;
        $this->twig = $twig;
        $this->templates = $templates;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
     * @param $panel
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public function getName(Profile $profile, $panel)
    {
        $templates = $this->getNames($profile);

        if (!isset($templates[$panel])) {
            throw new NotFoundHttpException(sprintf('Panel "%s" is not registered in profiler or is not present in viewed profile.', $panel));
        }

        return $templates[$panel];
    }

    /**
     * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
     * @return array
     */
    public function getTemplates(Profile $profile)
    {
        $templates = $this->getNames($profile);
        foreach ($templates as $name => $template) {
            $templates[$name] = $this->twig->loadTemplate($template);
        }

        return $templates;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Profiler\Profiler $profiler
     */
    public function setProfiler(Profiler $profiler)
    {
        $this->profiler = $profiler;
    }

    /**
     * Gets template names of templates that are
     * present in the viewed profile
     * @param \Symfony\Component\HttpKernel\Profiler\Profile $profile
     * @return array
     * @throws \UnexpectedValueException
     */
    protected function getNames(Profile $profile)
    {
        $templates = array();

        foreach ($this->templates as $arguments) {
            if (null === $arguments) {
                continue;
            }

            list($name, $template) = $arguments;

            if (!$this->profiler->has($name) || !$profile->hasCollector($name)) {
                continue;
            }

            if ('.html.twig' === substr($template, -10)) {
                $template = substr($template, 0, -10);
            }

            if (!$this->templating->exists($template.'.html.twig')) {
                throw new \UnexpectedValueException(sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
            }

            $templates[$name] = $template.'.html.twig';
        }

        return $templates;
    }
}