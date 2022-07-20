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
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Loader\ExistsLoaderInterface;
use Twig\Loader\SourceContextLoaderInterface;

/**
 * Profiler Templates Manager.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Artur Wielogórski <wodor@wodor.net>
 *
 * @internal since Symfony 4.4
 */
class TemplateManager
{
    protected $twig;
    protected $templates;
    protected $profiler;

    public function __construct(Profiler $profiler, Environment $twig, array $templates)
    {
        $this->profiler = $profiler;
        $this->twig = $twig;
        $this->templates = $templates;
    }

    /**
     * Gets the template name for a given panel.
     *
     * @param string $panel
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
     * Gets template names of templates that are present in the viewed profile.
     *
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    public function getNames(Profile $profile)
    {
        $templates = [];

        foreach ($this->templates as $arguments) {
            if (null === $arguments) {
                continue;
            }

            [$name, $template] = $arguments;

            if (!$this->profiler->has($name) || !$profile->hasCollector($name)) {
                continue;
            }

            if (str_ends_with($template, '.html.twig')) {
                $template = substr($template, 0, -10);
            }

            if (!$this->templateExists($template.'.html.twig', false)) {
                throw new \UnexpectedValueException(sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
            }

            $templates[$name] = $template.'.html.twig';
        }

        return $templates;
    }

    /**
     * @deprecated since Symfony 4.4
     */
    protected function templateExists($template/* , bool $triggerDeprecation = true */)
    {
        if (1 === \func_num_args()) {
            @trigger_error(sprintf('The "%s()" method is deprecated since Symfony 4.4, use the "exists()" method of the Twig loader instead.', __METHOD__), \E_USER_DEPRECATED);
        }

        $loader = $this->twig->getLoader();

        if (1 === Environment::MAJOR_VERSION && !$loader instanceof ExistsLoaderInterface) {
            try {
                if ($loader instanceof SourceContextLoaderInterface) {
                    $loader->getSourceContext($template);
                } else {
                    $loader->getSource($template);
                }

                return true;
            } catch (LoaderError $e) {
            }

            return false;
        }

        return $loader->exists($template);
    }
}
