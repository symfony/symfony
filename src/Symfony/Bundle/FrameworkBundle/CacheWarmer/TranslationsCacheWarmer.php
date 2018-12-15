<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\CacheWarmer;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Generates the catalogues for translations.
 *
 * @author Xavier Leune <xavier.leune@gmail.com>
 */
class TranslationsCacheWarmer implements CacheWarmerInterface, ServiceSubscriberInterface
{
    private $container;
    private $translator;

    /**
     * TranslationsCacheWarmer constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct($container)
    {
        // As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
        if ($container instanceof ContainerInterface) {
            $this->container = $container;
        } elseif ($container instanceof TranslatorInterface) {
            $this->translator = $container;
            @trigger_error(sprintf('Using a "%s" as first argument of %s is deprecated since Symfony 3.4 and will be unsupported in version 4.0. Use a %s instead.', TranslatorInterface::class, __CLASS__, ContainerInterface::class), E_USER_DEPRECATED);
        } else {
            throw new \InvalidArgumentException(sprintf('%s only accepts instance of Psr\Container\ContainerInterface as first argument.', __CLASS__));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function warmUp($cacheDir)
    {
        if (null === $this->translator) {
            $this->translator = $this->container->get('translator');
        }

        if ($this->translator instanceof WarmableInterface) {
            $this->translator->warmUp($cacheDir);
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
        return [
            'translator' => TranslatorInterface::class,
        ];
    }
}
