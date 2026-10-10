<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\JavaScriptImport;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The "Assets" tab of the interactive "debug" command.
 *
 * Reuses the same data source ({@see AssetMapperInterface}) as the
 * "debug:asset-map" command, so the detail pane shows the logical/public/source
 * paths and imports of the selected asset.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class AssetMapperDebugSection extends AbstractDebugSection
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly string $projectDir,
    ) {
    }

    public function getLabel(): string
    {
        return 'Assets';
    }

    public function getShortLabel(): string
    {
        return 'Assets';
    }

    public function describe(DebugItem $item, int $width): string
    {
        $asset = $this->assetMapper->getAsset($item->value);
        if (!$asset) {
            return \sprintf('Asset "%s" does not exist.', $item->value);
        }

        return $this->describeToBuffer(function (SymfonyStyle $io) use ($asset): void {
            $rows = [['Logical path' => $asset->logicalPath]];
            if (isset($asset->sourcePath)) {
                $rows[] = ['Source path' => $this->relativizePath($asset->sourcePath)];
            }
            if (isset($asset->publicPath)) {
                $rows[] = ['Public path' => $asset->publicPath];
            }
            if (isset($asset->publicExtension)) {
                $rows[] = ['Extension' => $asset->publicExtension];
            }
            $rows[] = ['Vendor' => $asset->isVendor ? 'yes' : 'no'];
            if (isset($asset->digest)) {
                $rows[] = ['Digest' => $asset->digest];
            }

            $io->definitionList(...$rows);

            if ($imports = $asset->getJavaScriptImports()) {
                $io->section('JavaScript imports');
                $io->listing(array_map(
                    static fn (JavaScriptImport $import): string => $import->importName.($import->isLazy ? ' (lazy)' : ''),
                    $imports,
                ));
            }
        });
    }

    /**
     * Builds the full, unfiltered item list once. Recomputing it on every keystroke
     * would be costly on large applications.
     *
     * @return list<DebugItem>
     */
    protected function buildItems(): array
    {
        $items = [];
        foreach ($this->assetMapper->allAssets() as $asset) {
            $items[] = new DebugItem('asset', $asset->logicalPath, $asset->logicalPath, searchText: implode("\n", array_filter([
                $asset->sourcePath ?? '',
                $asset->publicExtension ?? '',
                $asset->isVendor ? 'vendor' : '',
            ])) ?: null);
        }

        return $items;
    }

    private function relativizePath(string $path): string
    {
        return str_replace($this->projectDir.'/', '', $path);
    }
}
