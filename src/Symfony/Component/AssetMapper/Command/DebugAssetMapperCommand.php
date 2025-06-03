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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addArgument('name', InputArgument::OPTIONAL, 'An asset name (or a path) to search for (e.g. "app")')
            ->addOption('ext', null, InputOption::VALUE_REQUIRED, 'Filter assets by extension (e.g. "css")', null, ['js', 'css', 'png'])
            ->addOption('full', null, null, 'Whether to show the full paths')
            ->addOption('vendor', null, InputOption::VALUE_NEGATABLE, 'Only show assets from vendor packages')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command displays information about the Asset
Mapper for debugging purposes.

To list all configured paths (with local paths and their namespace prefixes) and
all mapped assets (with their logical path and filesystem path), run:

  <info>php %command.full_name%</info>

You can filter the results by providing a name to search for in the asset name
or path:

  <info>php %command.full_name% bootstrap.js</info>
  <info>php %command.full_name% style/</info>

To filter the assets by extension, use the <info>--ext</info> option:

  <info>php %command.full_name% --ext=css</info>

To show only assets from vendor packages, use the <info>--vendor</info> option:

  <info>php %command.full_name% --vendor</info>

To exclude assets from vendor packages, use the <info>--no-vendor</info> option:

  <info>php %command.full_name% --no-vendor</info>

To see the full paths, use the <info>--full</info> option:

    <info>php %command.full_name% --full</info>

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $extensionFilter = $input->getOption('ext');
        $vendorFilter = $input->getOption('vendor');

        if (!$extensionFilter) {
            $io->section($name ? 'Matched Paths' : 'Asset Mapper Paths');
            $pathRows = [];
            foreach ($this->assetMapperRepository->allDirectories() as $path => $namespace) {
                $path = $this->relativizePath($path);
                if (!$input->getOption('full')) {
                    $path = $this->shortenPath($path);
                }
                if ($name && !str_contains($path, $name) && !str_contains($namespace, $name)) {
                    continue;
                }
                $pathRows[] = [$path, $namespace];
            }
            uasort($pathRows, static function (array $a, array $b): int {
                return [(bool) $a[1], ...$a] <=> [(bool) $b[1], ...$b];
            });
            if ($pathRows) {
                $io->table(['Path', 'Namespace prefix'], $pathRows);
            } else {
                $io->warning('No paths found.');
            }
        }

        $io->section($name ? 'Matched Assets' : 'Mapped Assets');
        $rows = $this->searchAssets($name, $extensionFilter, $vendorFilter);
        if ($rows) {
            if (!$input->getOption('full')) {
                $rows = array_map(fn (array $row): array => [
                    $this->shortenPath($row[0]),
                    $this->shortenPath($row[1]),
                ], $rows);
            }
            uasort($rows, static function (array $a, array $b): int {
                return [$a] <=> [$b];
            });
            $io->table(['Logical Path', 'Filesystem Path'], $rows);
            if ($this->didShortenPaths) {
                $io->note('To see the full paths, re-run with the --full option.');
            }
        } else {
            $io->warning('No assets found.');
        }

        return 0;
    }

    /**
     * @return list<array{0:string, 1:string}>
     */
    private function searchAssets(?string $name, ?string $extension, ?bool $vendor): array
    {
        $rows = [];
        foreach ($this->assetMapper->allAssets() as $asset) {
            if ($extension && $extension !== $asset->publicExtension) {
                continue;
            }
            if (null !== $vendor && $vendor !== $asset->isVendor) {
                continue;
            }
            if ($name && !str_contains($asset->logicalPath, $name) && !str_contains($asset->sourcePath, $name)) {
                continue;
            }

            $logicalPath = $asset->logicalPath;
            $sourcePath = $this->relativizePath($asset->sourcePath);

            $rows[] = [
                $logicalPath,
                $sourcePath,
            ];
        }

        return $rows;
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
