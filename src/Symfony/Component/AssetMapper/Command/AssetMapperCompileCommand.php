<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Command;

use Symfony\Component\AssetMapper\AssetMapper;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\CompiledAssetMapperConfigReader;
use Symfony\Component\AssetMapper\Event\PreAssetsCompileEvent;
use Symfony\Component\AssetMapper\ImportMap\ImportMapGenerator;
use Symfony\Component\AssetMapper\Path\PublicAssetsFilesystemInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Compiles the assets in the asset mapper to the final output directory.
 *
 * This command is intended to be used during deployment.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'asset-map:compile', description: 'Compile all mapped assets and writes them to the final public output directory')]
final class AssetMapperCompileCommand extends Command
{
    public function __construct(
        private readonly CompiledAssetMapperConfigReader $compiledConfigReader,
        private readonly AssetMapperInterface $assetMapper,
        private readonly ImportMapGenerator $importMapGenerator,
        private readonly PublicAssetsFilesystemInterface $assetsFilesystem,
        private readonly string $projectDir,
        private readonly bool $isDebug,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command compiles and dumps all the assets in
the asset mapper into the final public directory (usually <comment>public/assets</comment>).

This command is meant to be run during deployment.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->eventDispatcher?->dispatch(new PreAssetsCompileEvent($io));

        // remove existing config files
        $this->compiledConfigReader->removeConfig(AssetMapper::MANIFEST_FILE_NAME);
        $this->compiledConfigReader->removeConfig(ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME);
        $entrypointFiles = [];
        foreach ($this->importMapGenerator->getEntrypointNames() as $entrypointName) {
            $path = \sprintf(ImportMapGenerator::ENTRYPOINT_CACHE_FILENAME_PATTERN, $entrypointName);
            $this->compiledConfigReader->removeConfig($path);
            $entrypointFiles[$entrypointName] = $path;
        }

        $manifest = $this->createManifestAndWriteFiles($io);
        $manifestPath = $this->compiledConfigReader->saveConfig(AssetMapper::MANIFEST_FILE_NAME, $manifest);
        $io->comment(\sprintf('Manifest written to <info>%s</info>', $this->shortenPath($manifestPath)));

        $importMapPath = $this->compiledConfigReader->saveConfig(ImportMapGenerator::IMPORT_MAP_CACHE_FILENAME, $this->importMapGenerator->getRawImportMapData());
        $io->comment(\sprintf('Import map data written to <info>%s</info>.', $this->shortenPath($importMapPath)));

        foreach ($entrypointFiles as $entrypointName => $path) {
            $this->compiledConfigReader->saveConfig($path, $this->importMapGenerator->findEagerEntrypointImports($entrypointName));
        }
        $styledEntrypointNames = array_map(fn (string $entrypointName) => \sprintf('<info>%s</>', $entrypointName), array_keys($entrypointFiles));
        $io->comment(\sprintf('Entrypoint metadata written for <comment>%d</> entrypoints (%s).', \count($entrypointFiles), implode(', ', $styledEntrypointNames)));

        if ($this->isDebug) {
            $io->warning(\sprintf(
                'Debug mode is enabled in your project: Symfony will not serve any changed assets until you delete the files in the "%s" directory again.',
                $this->shortenPath(\dirname($manifestPath))
            ));
        }

        return 0;
    }

    private function shortenPath(string $path): string
    {
        return str_replace($this->projectDir.'/', '', $path);
    }

    private function createManifestAndWriteFiles(SymfonyStyle $io): array
    {
        $io->comment(\sprintf('Compiling and writing asset files to <info>%s</info>', $this->shortenPath($this->assetsFilesystem->getDestinationPath())));
        $manifest = [];
        foreach ($this->assetMapper->allAssets() as $asset) {
            if (null !== $asset->content) {
                // The original content has been modified by the AssetMapperCompiler
                $this->assetsFilesystem->write($asset->publicPath, $asset->content);
            } else {
                $this->assetsFilesystem->copy($asset->sourcePath, $asset->publicPath);
            }

            $manifest[$asset->logicalPath] = $asset->publicPath;
        }
        ksort($manifest);
        $io->comment(\sprintf('Compiled <info>%d</info> assets', \count($manifest)));

        return $manifest;
    }
}
