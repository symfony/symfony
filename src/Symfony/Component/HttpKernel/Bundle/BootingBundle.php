<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;

/**
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
class BootingBundle implements BundleInterface
{
    private $innerBundle;

    public function __construct(BundleInterface $innerBundle)
    {
        $this->innerBundle = $innerBundle;
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
    public function build(ContainerBuilder $container)
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension()
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return $this->innerBundle->getParent();
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->innerBundle->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return $this->innerBundle->getNamespace();
    }

    /**
     * {@inheritdoc}
     */
    public function getPath()
    {
        return $this->innerBundle->getPath();
    }

    /**
     * {@inheritdoc}
     */
    public function setContainer(ContainerInterface $container = null)
    {
        throw new BadMethodCallException(sprintf('Calling "%s()" is not allowed.', __METHOD__));
    }
}
