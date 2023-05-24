<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\ImportMap\Resolver;

use Psr\Container\ContainerInterface;

/**
 * @experimental
 */
final class PackageResolver implements PackageResolverInterface
{
    public function __construct(
        private readonly string $provider,
        private readonly ContainerInterface $locator,
    ) {
    }

    public function resolvePackages(array $packagesToRequire): array
    {
        return $this->locator->get($this->provider)
            ->resolvePackages($packagesToRequire);
    }
}
