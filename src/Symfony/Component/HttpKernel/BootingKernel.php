<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Bundle\BootingBundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class BootingKernel implements KernelInterface
{
    private $innerKernel;

    public function __construct(KernelInterface $innerKernel)
    {
        $this->innerKernel = $innerKernel;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        return $this->innerKernel->serialize();
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        $this->innerKernel->unserialize($serialized);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles()
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function boot()
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function shutdown()
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function getBundles()
    {
        return array_map(function (BundleInterface $bundle) {
            return new BootingBundle($bundle);
        }, $this->innerKernel->getBundles());
    }

    /**
     * {@inheritdoc}
     */
    public function getBundle($name, $first = true)
    {
        if (is_array($bundle = $this->innerKernel->getBundle($name, $first))) {
            return array_map(function (BundleInterface $bundle) {
                return new BootingBundle($bundle);
            }, $bundle);
        }

        return new BootingBundle($bundle);
    }

    /**
     * {@inheritdoc}
     */
    public function locateResource($name, $dir = null, $first = true)
    {
        return $this->innerKernel->locateResource($name, $dir, $first);
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->innerKernel->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvironment()
    {
        return $this->innerKernel->getEnvironment();
    }

    /**
     * {@inheritdoc}
     */
    public function isDebug()
    {
        return $this->innerKernel->isDebug();
    }

    /**
     * {@inheritdoc}
     */
    public function getRootDir()
    {
        return $this->innerKernel->getRootDir();
    }

    /**
     * {@inheritdoc}
     */
    public function getContainer()
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function getStartTime()
    {
        return $this->innerKernel->getStartTime();
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheDir()
    {
        return $this->innerKernel->getCacheDir();
    }

    /**
     * {@inheritdoc}
     */
    public function getLogDir()
    {
        return $this->innerKernel->getLogDir();
    }

    /**
     * {@inheritdoc}
     */
    public function getCharset()
    {
        return $this->innerKernel->getCharset();
    }
}
