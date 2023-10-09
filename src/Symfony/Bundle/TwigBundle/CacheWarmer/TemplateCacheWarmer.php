<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\CacheWarmer;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Environment;
use Twig\Error\Error;

/**
 * Generates the Twig cache for all templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    private ContainerInterface $container;
    private Environment $twig;
    private iterable $iterator;

    public function __construct(ContainerInterface $container, iterable $iterator)
    {
        // As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
        $this->container = $container;
        $this->iterator = $iterator;
    }

    /**
     * @param string|null $buildDir
     */
    public function warmUp(string $cacheDir /* , string $buildDir = null */): array
    {
        $this->twig ??= $this->container->get('twig');

        foreach ($this->iterator as $template) {
            try {
                $this->twig->load($template);
            } catch (Error) {
                /*
                 * Problem during compilation, give up for this template (e.g. syntax errors).
                 * Failing silently here allows to ignore templates that rely on functions that aren't available in
                 * the current environment. For example, the WebProfilerBundle shouldn't be available in the prod
                 * environment, but some templates that are never used in prod might rely on functions the bundle provides.
                 * As we can't detect which templates are "really" important, we try to load all of them and ignore
                 * errors. Error checks may be performed by calling the lint:twig command.
                 */
            }
        }

        return [];
    }

    public function isOptional(): bool
    {
        return true;
    }

    public static function getSubscribedServices(): array
    {
        return [
            'twig' => Environment::class,
        ];
    }
}
