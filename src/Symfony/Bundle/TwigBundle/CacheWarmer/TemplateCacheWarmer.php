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
 *
 * @final since Symfony 7.1
 */
class TemplateCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    private Environment $twig;

    public function __construct(private ContainerInterface $container, private iterable $iterator, private string $cacheFolder = 'twig')
    {
        if (\func_num_args() < 3) {
            trigger_deprecation('symfony/twig-bundle', '7.1', 'The "string $cacheFolder" argument of "%s()" method will not be optional anymore in version 8.0, not defining it is deprecated.', __METHOD__);
        }
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$buildDir) {
            return [];
        }

        $this->twig ??= $this->container->get('twig');

        $originalCache = $this->twig->getCache();
        $this->twig->setCache($buildDir.\DIRECTORY_SEPARATOR.$this->cacheFolder);

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

        $this->twig->setCache($originalCache);

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
