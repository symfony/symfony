<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler;

use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\MappedAsset;

/**
 * Resolves import paths in JS files.
 *
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
final class JavaScriptImportPathCompiler implements AssetCompilerInterface
{
    use AssetCompilerPathResolverTrait;

    // https://regex101.com/r/VFdR4H/1
    private const IMPORT_PATTERN = '/(?:import\s+(?:(?:\*\s+as\s+\w+|[\w\s{},*]+)\s+from\s+)?|\bimport\()\s*[\'"`](\.\/[^\'"`]+|(\.\.\/)+[^\'"`]+)[\'"`]\s*[;\)]?/m';

    public function __construct(private readonly bool $strictMode = true)
    {
    }

    public function compile(string $content, MappedAsset $asset, AssetMapperInterface $assetMapper): string
    {
        return preg_replace_callback(self::IMPORT_PATTERN, function ($matches) use ($asset, $assetMapper) {
            $resolvedPath = $this->resolvePath(\dirname($asset->logicalPath), $matches[1]);

            $dependentAsset = $assetMapper->getAsset($resolvedPath);

            if (!$dependentAsset && $this->strictMode) {
                $message = sprintf('Unable to find asset "%s" imported from "%s".', $resolvedPath, $asset->getSourcePath());

                if (null !== $assetMapper->getAsset(sprintf('%s.js', $resolvedPath))) {
                    $message .= sprintf(' Try adding ".js" to the end of the import - i.e. "%s.js".', $resolvedPath);
                }

                throw new \RuntimeException($message);
            }

            if ($dependentAsset && $this->supports($dependentAsset)) {
                // If we found the path and it's a JavaScript file, list it as a dependency.
                // This will cause the asset to be included in the importmap.
                $isLazy = str_contains($matches[0], 'import(');

                $asset->addDependency($dependentAsset, $isLazy);
            }

            return $matches[0];
        }, $content);
    }

    public function supports(MappedAsset $asset): bool
    {
        return 'application/javascript' === $asset->getMimeType()  || 'text/javascript' === $asset->getMimeType();
    }
}
