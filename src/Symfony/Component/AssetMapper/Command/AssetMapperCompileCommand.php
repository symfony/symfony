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
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Compiles the assets in the asset mapper to the final output directory.
 *
 * This command is intended to be used during deployment.
 *
 * @experimental
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'assetmap:compile', description: 'Compiles all mapped assets and writes them to the final public output directory.')]
final class AssetMapperCompileCommand extends Command
{
    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly ImportMapManager $importMapManager,
        private readonly Filesystem $filesystem,
        private readonly string $projectDir,
        private readonly string $publicDirName,
        private readonly bool $isDebug,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('clean', null, null, 'Whether to clean the public directory before compiling assets')
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
        $publicDir = $this->projectDir.'/'.$this->publicDirName;
        if (!is_dir($publicDir)) {
            throw new InvalidArgumentException(sprintf('The public directory "%s" does not exist.', $publicDir));
        }

        if ($input->getOption('clean')) {
            $outputDir = $publicDir.$this->assetMapper->getPublicPrefix();
            $io->comment(sprintf('Cleaning <info>%s</info>', $outputDir));
            $this->filesystem->remove($outputDir);
            $this->filesystem->mkdir($outputDir);
        }

        $allAssets = $this->assetMapper->allAssets();

        $io->comment(sprintf('Compiling <info>%d</info> assets to <info>%s%s</info>', \count($allAssets), $publicDir, $this->assetMapper->getPublicPrefix()));
        $manifest = [];
        foreach ($allAssets as $asset) {
            // $asset->getPublicPath() will start with a "/"
            $targetPath = $publicDir.$asset->getPublicPath();

            if (!is_dir($dir = \dirname($targetPath))) {
                $this->filesystem->mkdir($dir);
            }

            $this->filesystem->dumpFile($targetPath, $asset->getContent());
            $manifest[$asset->logicalPath] = $asset->getPublicPath();
        }

        $manifestPath = $publicDir.$this->assetMapper->getPublicPrefix().AssetMapper::MANIFEST_FILE_NAME;
        $this->filesystem->dumpFile($manifestPath, json_encode($manifest, \JSON_PRETTY_PRINT));
        $io->comment(sprintf('Manifest written to <info>%s</info>', $manifestPath));

        $importMapPath = $publicDir.$this->assetMapper->getPublicPrefix().ImportMapManager::IMPORT_MAP_FILE_NAME;
        $this->filesystem->dumpFile($importMapPath, $this->importMapManager->getImportMapJson());
        $io->comment(sprintf('Import map written to <info>%s</info>', $importMapPath));

        if ($this->isDebug) {
            $io->warning(sprintf(
                'You are compiling assets in development. Symfony will not serve any changed assets until you delete %s and %s.',
                $manifestPath,
                $importMapPath
            ));
        }

        return self::SUCCESS;
    }
}
