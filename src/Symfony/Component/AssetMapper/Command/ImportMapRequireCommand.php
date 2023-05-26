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
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
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
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to add')
            ->addOption('download', 'd', InputOption::VALUE_NONE, 'Download packages locally')
            ->addOption('preload', 'p', InputOption::VALUE_NONE, 'Preload packages')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The local path where the package lives relative to the project root')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command adds packages to <comment>importmap.php</comment> usually
by finding a CDN URL for the given package and version.

For example:

    <info>php %command.full_name% lodash --preload</info>
    <info>php %command.full_name% "lodash@^4.15"</info>

You can also require specific paths of a package:

    <info>php %command.full_name% "chart.js/auto"</info>

Or download one package/file, but alias its name in your import map:

    <info>php %command.full_name% "vue/dist/vue.esm-bundler.js=vue"</info>

The <info>preload</info> option will set the <info>preload</info> option in the importmap,
which will tell the browser to preload the package. This should be used for all
critical packages that are needed on page load.

The <info>download</info> option will download the package locally and point the
importmap to it. Use this if you want to avoid using a CDN or if you want to
ensure that the package is available even if the CDN is down.

Sometimes, a package may require other packages and multiple new items may be added
to the import map.

You can also require multiple packages at once:

    <info>php %command.full_name% "lodash@^4.15" "@hotwired/stimulus"</info>

EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $packageList = $input->getArgument('packages');
        $path = null;
        if ($input->getOption('path')) {
            if (\count($packageList) > 1) {
                $io->error('The "--path" option can only be used when you require a single package.');

                return Command::FAILURE;
            }

            $path = $input->getOption('path');
            if (!is_file($path)) {
                $path = $this->projectDir.'/'.$path;

                if (!is_file($path)) {
                    $io->error(sprintf('The path "%s" does not exist.', $input->getOption('path')));

                    return Command::FAILURE;
                }
            }
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
                $parts['alias'] ?? $parts['package'],
                isset($parts['registry']) && $parts['registry'] ? $parts['registry'] : null,
                $path,
            );
        }

        if ($input->getOption('download')) {
            $io->warning(sprintf('The --download option is experimental. It should work well with the default %s provider but check your browser console for 404 errors.', ImportMapManager::PROVIDER_JSDELIVR_ESM));
        }

        $newPackages = $this->importMapManager->require($packages);
        if (1 === \count($newPackages)) {
            $newPackage = $newPackages[0];
            $message = sprintf('Package "%s" added to importmap.php', $newPackage->importName);

            if ($newPackage->isDownloaded && null !== $downloadedAsset = $this->assetMapper->getAsset($newPackage->path)) {
                $application = $this->getApplication();
                if ($application instanceof Application) {
                    $projectDir = $application->getKernel()->getProjectDir();
                    $downloadedPath = $downloadedAsset->sourcePath;
                    if (str_starts_with($downloadedPath, $projectDir)) {
                        $downloadedPath = substr($downloadedPath, \strlen($projectDir) + 1);
                    }

                    $message .= sprintf(' and downloaded locally to "%s"', $downloadedPath);
                }
            }

            $message .= '.';
        } else {
            $names = array_map(fn (ImportMapEntry $package) => $package->importName, $newPackages);
            $message = sprintf('%d new packages (%s) added to the importmap.php!', \count($newPackages), implode(', ', $names));
        }

        $messages = [$message];

        if (1 === \count($newPackages)) {
            $messages[] = sprintf('Use the new package normally by importing "%s".', $newPackages[0]->importName);
        }

        $io->success($messages);

        return Command::SUCCESS;
    }
}
