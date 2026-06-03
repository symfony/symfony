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
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface as BaseBundleInterface;

/**
 * @internal
 */
final class BundleAdapter implements BundleInterface
{
    private string $namespace;

    public function __construct(
        private readonly BaseBundleInterface $bundle,
    ) {
    }

    public function boot(): void
    {
        $this->bundle->boot();
    }

    public function shutdown(): void
    {
        $this->bundle->shutdown();
    }

    public function build(ContainerBuilder $container): void
    {
        $this->bundle->build($container);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return $this->bundle->getContainerExtension();
    }

    public function getName(): string
    {
        return $this->bundle->getName();
    }

    public function getNamespace(): string
    {
        return $this->namespace ??= false === ($pos = strrpos($this->bundle::class, '\\')) ? '' : substr($this->bundle::class, 0, $pos);
    }

    public function getPath(): string
    {
        return $this->bundle->getPath();
    }

    public function setContainer(?ContainerInterface $container): void
    {
        $this->bundle->setContainer($container);
    }
}
