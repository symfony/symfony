<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Css;

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;
use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * Loads stylesheets mapped by the AssetMapper component.
 *
 * Both spellings a template can produce are understood: the logical path
 * (<link rel="stylesheet" href="styles/app.css">) and the public, digested
 * path that asset() returns (/assets/styles/app-3f2a8c1d.css). The compiled
 * content is used, so the AssetMapper compilers apply, and each bundle or
 * plugin can map its own namespaced assets directory.
 *
 * @experimental
 */
final class AssetMapperStylesheetLoader implements StylesheetLoaderInterface
{
    /** @var array<string, string>|null Logical paths by public path */
    private ?array $publicPaths = null;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
    ) {
    }

    public function load(string $href): string
    {
        $path = parse_url($href, \PHP_URL_PATH) ?: $href;

        $asset = $this->assetMapper->getAsset(ltrim($path, '/'))
            ?? $this->findByPublicPath($path)
            ?? throw new InvalidArgumentException(\sprintf('The stylesheet "%s" is not a mapped asset.', $href));

        if (null !== $asset->content) {
            return $asset->content;
        }
        // Only a local file selected by the application's asset map can be read.
        /**
         * @psalm-taint-escape file
         * @psalm-taint-escape ssrf
         * @psalm-taint-escape url_component
         * @psalm-taint-escape url_path
         */
        $source = realpath($asset->sourcePath);
        if (false === $source || !is_file($source) || !is_readable($source)) {
            throw new InvalidArgumentException(\sprintf('The stylesheet "%s" has no readable local source.', $href));
        }

        return (string) file_get_contents($source);
    }

    private function findByPublicPath(string $path): ?MappedAsset
    {
        if (null === $this->publicPaths) {
            $this->publicPaths = [];
            foreach ($this->assetMapper->allAssets() as $asset) {
                $this->publicPaths[$asset->publicPath] = $asset->logicalPath;
                $this->publicPaths[$asset->publicPathWithoutDigest] ??= $asset->logicalPath;
            }
        }

        $logicalPath = $this->publicPaths[$path] ?? $this->publicPaths['/'.ltrim($path, '/')] ?? null;

        return null === $logicalPath ? null : $this->assetMapper->getAsset($logicalPath);
    }
}
