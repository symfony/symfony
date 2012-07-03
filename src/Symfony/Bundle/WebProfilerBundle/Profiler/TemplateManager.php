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
use Symfony\Component\Templating\EngineInterface;

/**
 * Profiler Templates Manager
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Artur Wielogórski <wodor@wodor.net>
 */
class TemplateManager
{
    protected $templating;
    protected $twig;
    protected $templates;
    protected $profiler;

    /**
     * Constructor.
     *
     * @param Profiler          $profiler
     * @param TwigEngine        $templating
     * @param \Twig_Environment $twig
     * @param array             $templates
     */
    public function __construct(Profiler $profiler, EngineInterface $templating, \Twig_Environment $twig, array $templates)
    {
        $this->profiler = $profiler;
        $this->templating = $templating;
        $this->twig = $twig;
        $this->templates = $templates;
    }

    /**
     * Gets the template name for a given panel.
     *
     * @param Profile $profile
     * @param string  $panel
     *
     * @return mixed
     *
     * @throws NotFoundHttpException
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
     * Gets the templates for a given profile.
     *
     * @param Profile $profile
     *
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
     * Gets template names of templates that are present in the viewed profile.
     *
     * @param Profile $profile
     *
     * @return array
     *
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
