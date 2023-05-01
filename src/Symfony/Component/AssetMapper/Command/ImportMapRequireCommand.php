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

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @experimental
 *
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:require', description: 'Requires JavaScript packages')]
final class ImportMapRequireCommand extends Command
{
    public function __construct(
        private readonly ImportMapManager $importMapManager,
        private readonly AssetMapperInterface $assetMapper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to add');
        $this->addOption('download', 'd', InputOption::VALUE_NONE, 'Download packages locally');
        $this->addOption('preload', 'p', InputOption::VALUE_NONE, 'Preload packages');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $packageList = $input->getArgument('packages');
        if ($input->hasOption('path') && \count($packageList) > 1) {
            $io->error('The "--path" option can only be used when you require a single package.');

            return Command::FAILURE;
        }

        $packages = [];
        foreach ($packageList as $packageName) {
            $parts = ImportMapManager::parsePackageName($packageName);
            if (null === $parts) {
                $io->error(sprintf('Package "%s" is not a valid package name format. Use the format PACKAGE@VERSION - e.g. "lodash" or "lodash@^4"', $packageName));

                return Command::FAILURE;
            }

            $packages[] = new PackageRequireOptions(
                $parts['package'],
                $parts['version'] ?? null,
                $input->getOption('download'),
                $input->getOption('preload'),
                null,
                isset($parts['registry']) && $parts['registry'] ? $parts['registry'] : null,
            );
        }

        $newPackages = $this->importMapManager->require($packages);
        if (1 === \count($newPackages)) {
            $newPackage = $newPackages[0];
            $message = sprintf('Package "%s" added to importmap.php', $newPackage->importName);

            if ($newPackage->isDownloaded && null !== $downloadedAsset = $this->assetMapper->getAsset($newPackage->path)) {
                $application = $this->getApplication();
                if ($application instanceof Application) {
                    $projectDir = $application->getKernel()->getProjectDir();
                    $downloadedPath = $downloadedAsset->getSourcePath();
                    if (str_starts_with($downloadedPath, $projectDir)) {
                        $downloadedPath = substr($downloadedPath, \strlen($projectDir) + 1);
                    }

                    $message .= sprintf(' and downloaded locally to "%s"', $downloadedPath);
                }
            }

            $message .= '.';
        } else {
            $message = sprintf('%d new packages (%s) added to the importmap.php!', \count($newPackages), implode(', ', array_keys($newPackages)));
        }

        $messages = [$message];

        if (1 === \count($newPackages)) {
            $messages[] = sprintf('Use the new package normally by importing "%s".', $newPackages[0]->importName);
        }

        $io->success($messages);

        return Command::SUCCESS;
    }
}
