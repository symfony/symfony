<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fixtures;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class KernelForTest extends Kernel
{
    public function __construct(string $environment, bool $debug, private readonly bool $fakeContainer = true)
    {
        parent::__construct($environment, $debug);
    }

    public function getBundleMap(): array
    {
        return [];
    }

    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    protected function initializeContainer(): void
    {
        if ($this->fakeContainer) {
            $this->container = new ContainerBuilder();
        } else {
            parent::initializeContainer();
        }
    }
}
