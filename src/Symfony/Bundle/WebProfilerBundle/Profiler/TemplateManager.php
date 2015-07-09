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

/**
 * Profiler Templates Manager.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Artur Wielogórski <wodor@wodor.net>
 */
class TemplateManager
{
    protected $twig;
    protected $templates;
    protected $profiler;

    /**
     * Constructor.
     *
     * @param Profiler          $profiler
     * @param \Twig_Environment $twig
     * @param array             $templates
     */
    public function __construct(Profiler $profiler, \Twig_Environment $twig, array $templates)
    {
        $this->profiler = $profiler;
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
        $templates = $this->reorderTemplates($templates);

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

            if (!$this->templateExists($template.'.html.twig')) {
                throw new \UnexpectedValueException(sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
            }

            $templates[$name] = $template.'.html.twig';
        }

        return $templates;
    }

    // to be removed when the minimum required version of Twig is >= 2.0
    protected function templateExists($template)
    {
        $loader = $this->twig->getLoader();
        if ($loader instanceof \Twig_ExistsLoaderInterface) {
            return $loader->exists($template);
        }

        try {
            $loader->getSource($template);

            return true;
        } catch (\Twig_Error_Loader $e) {
        }

        return false;
    }

    /**
     * It changes the default order of collector templates to show them in a
     * different order which is better for design and aesthetic reasons.
     *
     * @param array $templates
     * @return array
     */
    protected function reorderTemplates($templates)
    {
        $templates = $this->moveArrayElementToFirstPosition($templates, 'twig');
        $templates = $this->moveArrayElementToFirstPosition($templates, 'memory');
        $templates = $this->moveArrayElementToFirstPosition($templates, 'time');
        $templates = $this->moveArrayElementToFirstPosition($templates, 'request');

        $templates = $this->moveArrayElementToLastPosition($templates, 'config');

        return $templates;
    }

    private function moveArrayElementToFirstPosition($array, $key)
    {
        if (!array_key_exists($key, $array)) {
            return $array;
        }

        $value = $array[$key];
        unset($array[$key]);

        return array_merge(array($key => $value), $array);
    }

    private function moveArrayElementToLastPosition($array, $key)
    {
        if (!array_key_exists($key, $array)) {
            return $array;
        }

        $value = $array[$key];
        unset($array[$key]);
        $array[$key] = $value;

        return $array;
    }
}
