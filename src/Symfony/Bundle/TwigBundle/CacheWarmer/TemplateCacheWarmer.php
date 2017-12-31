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
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Twig\Environment;
use Twig\Error\Error;

/**
 * Generates the Twig cache for all templates.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TemplateCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    private $container;
    private $twig;
    private $iterator;

    /**
     * TemplateCacheWarmer constructor.
     *
     * @param ContainerInterface $container
     * @param \Traversable       $iterator
     */
    public function __construct($container, \Traversable $iterator)
    {
        // As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
        if ($container instanceof ContainerInterface) {
            $this->container = $container;
        } elseif ($container instanceof Environment) {
            $this->twig = $container;
            @trigger_error(sprintf('Using a "%s" as first argument of %s is deprecated since Symfony 3.4 and will be unsupported in version 4.0. Use a %s instead.', Environment::class, __CLASS__, ContainerInterface::class), E_USER_DEPRECATED);
        } else {
            throw new \InvalidArgumentException(sprintf('%s only accepts instance of Psr\Container\ContainerInterface as first argument.', __CLASS__));
        }

        $this->iterator = $iterator;
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        if (null === $this->twig) {
            $this->twig = $this->container->get('twig');
        }

        foreach ($this->iterator as $template) {
            try {
                $this->twig->loadTemplate($template);
            } catch (Error $e) {
                // problem during compilation, give up
                // might be a syntax error or a non-Twig template
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isOptional()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedServices()
    {
        return array(
            'twig' => Environment::class,
        );
    }
}
