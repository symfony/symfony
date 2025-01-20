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

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Artur Wielogórski <wodor@wodor.net>
 *
 * @internal
 */
class TemplateManager
{
    public function __construct(
        protected Profiler $profiler,
        protected Environment $twig,
        protected array $templates,
    ) {
    }

    /**
     * Gets the template name for a given panel.
     *
     * @throws NotFoundHttpException
     */
    public function getName(Profile $profile, string $panel): mixed
    {
        $templates = $this->getNames($profile);

        if (!isset($templates[$panel])) {
            throw new NotFoundHttpException(\sprintf('Panel "%s" is not registered in profiler or is not present in viewed profile.', $panel));
        }

        return $templates[$panel];
    }

    /**
     * Gets template names of templates that are present in the viewed profile.
     *
     * @throws \UnexpectedValueException
     */
    public function getNames(Profile $profile): array
    {
        $loader = $this->twig->getLoader();
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

            if (!$loader->exists($template.'.html.twig')) {
                throw new \UnexpectedValueException(\sprintf('The profiler template "%s.html.twig" for data collector "%s" does not exist.', $template, $name));
            }

            $templates[$name] = $template.'.html.twig';
        }

        return $templates;
    }
}
