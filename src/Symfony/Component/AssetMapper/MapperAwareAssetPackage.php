<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper;

use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates asset packages to support resolving assets from the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class MapperAwareAssetPackage implements PackageInterface
{
    private readonly ?string $devServerPrefix;
    private readonly ?string $publicPrefix;

    /**
     * @param string|null           $devServerPublicPrefix      The public prefix served by AssetMapperDevServerSubscriber, null when it is disabled
     * @param PackageInterface|null $innerPackageWithoutVersion The decorated package without its version strategy, null when the Asset component is not configured
     * @param string|null           $publicPrefix               The public prefix of the mapped assets, used to recognize a path the mapper already resolved
     */
    public function __construct(
        private readonly PackageInterface $innerPackage,
        private readonly AssetMapperInterface $assetMapper,
        private readonly ?RequestStack $requestStack = null,
        ?string $devServerPublicPrefix = null,
        private readonly ?PackageInterface $innerPackageWithoutVersion = null,
        ?string $publicPrefix = null,
    ) {
        $this->devServerPrefix = null === $devServerPublicPrefix ? null : '/'.trim($devServerPublicPrefix, '/').'/';
        $this->publicPrefix = null === $publicPrefix ? null : '/'.trim($publicPrefix, '/').'/';
    }

    public function getVersion(string $path): string
    {
        return $this->innerPackage->getVersion($path);
    }

    public function getUrl(string $path): string
    {
        $publicPath = $this->assetMapper->getPublicPath($path);

        // the content hash is the version, so the configured strategy must not add a second one.
        // The import map renders public paths, which the mapper does not resolve again, hence the prefix test.
        // The package must be picked before the block below prepends the front controller.
        $package = $publicPath || (null !== $this->publicPrefix && str_starts_with('/'.$path, $this->publicPrefix))
            ? $this->innerPackageWithoutVersion ?? $this->innerPackage
            : $this->innerPackage;

        if ($publicPath) {
            $path = ltrim($publicPath, '/');
        }

        if (null !== $this->devServerPrefix && str_starts_with('/'.$path, $this->devServerPrefix)) {
            // the dev server serves those assets through the kernel, so the front controller must be part of the URL
            $request = $this->requestStack?->getMainRequest();
            $frontController = $request ? trim(substr($request->getBaseUrl(), \strlen($request->getBasePath())), '/') : '';

            if ('' !== $frontController) {
                $path = $frontController.'/'.$path;
            }
        }

        return $package->getUrl($path);
    }
}
