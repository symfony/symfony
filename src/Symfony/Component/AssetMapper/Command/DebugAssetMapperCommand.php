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

use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\AssetMapperRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Outputs all the assets in the asset mapper.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 */
#[AsCommand(name: 'debug:asset-map', description: 'Output all mapped assets')]
final class DebugAssetMapperCommand extends Command
{
    private bool $didShortenPaths = false;

    public function __construct(
        private readonly AssetMapperInterface $assetMapper,
        private readonly AssetMapperRepository $assetMapperRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('full', null, null, 'Whether to show the full paths')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command outputs all of the assets in
asset mapper for debugging purposes.
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $allAssets = $this->assetMapper->allAssets();

        $pathRows = [];
        foreach ($this->assetMapperRepository->allDirectories() as $path => $namespace) {
            $path = $this->relativizePath($path);
            if (!$input->getOption('full')) {
                $path = $this->shortenPath($path);
            }

            $pathRows[] = [$path, $namespace];
        }
        $io->section('Asset Mapper Paths');
        $io->table(['Path', 'Namespace prefix'], $pathRows);

        $rows = [];
        foreach ($allAssets as $asset) {
            $logicalPath = $asset->logicalPath;
            $sourcePath = $this->relativizePath($asset->sourcePath);

            if (!$input->getOption('full')) {
                $logicalPath = $this->shortenPath($logicalPath);
                $sourcePath = $this->shortenPath($sourcePath);
            }

            $rows[] = [
                $logicalPath,
                $sourcePath,
            ];
        }
        $io->section('Mapped Assets');
        $io->table(['Logical Path', 'Filesystem Path'], $rows);

        if ($this->didShortenPaths) {
            $io->note('To see the full paths, re-run with the --full option.');
        }

        return 0;
    }

    private function relativizePath(string $path): string
    {
        return str_replace($this->projectDir.'/', '', $path);
    }

    private function shortenPath(string $path): string
    {
        $limit = 50;

        if (\strlen($path) <= $limit) {
            return $path;
        }

        $this->didShortenPaths = true;
        $limit = floor(($limit - 3) / 2);

        return substr($path, 0, $limit).'...'.substr($path, -$limit);
    }
}
