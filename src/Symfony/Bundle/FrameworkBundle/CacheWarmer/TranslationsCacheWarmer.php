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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Generates the catalogues for translations.
 *
 * @author Xavier Leune <xavier.leune@gmail.com>
 */
class TranslationsCacheWarmer implements CacheWarmerInterface
{
    private $container;
    private $translator;

    /**
     * TranslationsCacheWarmer constructor.
     *
     * @param ContainerInterface|TranslatorInterface $container
     */
    public function __construct($container)
    {
        // As this cache warmer is optional, dependencies should be lazy-loaded, that's why a container should be injected.
        if ($container instanceof ContainerInterface) {
            $this->container = $container;
        } elseif ($container instanceof TranslatorInterface) {
            $this->translator = $container;
        } else {
            throw new \InvalidArgumentException(sprintf('%s only accepts instance of Symfony\Component\DependencyInjection\ContainerInterface or Symfony\Component\Translation\TranslatorInterface as first argument.', __CLASS__));
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
}
