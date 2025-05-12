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

use Symfony\Component\AssetMapper\ImportMap\ImportMapEntries;
use Symfony\Component\AssetMapper\ImportMap\ImportMapEntry;
use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\AssetMapper\ImportMap\ImportMapVersionChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageRequireOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:require', description: 'Require JavaScript packages')]
final class ImportMapRequireCommand extends Command
{
    use VersionProblemCommandTrait;

    public function __construct(
        private readonly ImportMapManager $importMapManager,
        private readonly ImportMapVersionChecker $importMapVersionChecker,
        private readonly ?string $projectDir = null,
    ) {
        if (null === $projectDir) {
            trigger_deprecation('symfony/asset-mapper', '7.3', 'The "%s()" method will have a new `string $projectDir` argument in version 8.0, not defining it is deprecated.', __METHOD__);
        }

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to add')
            ->addOption('entrypoint', null, InputOption::VALUE_NONE, 'Make the packages an entrypoint?')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'The local path where the package lives relative to the project root')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate the installation of the packages')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command adds packages to <comment>importmap.php</comment> usually
by finding a CDN URL for the given package and version.

For example:

    <info>php %command.full_name% lodash</info>
    <info>php %command.full_name% "lodash@^4.15"</info>

You can also require specific paths of a package:

    <info>php %command.full_name% "chart.js/auto"</info>

Or require one package/file, but alias its name in your import map:

    <info>php %command.full_name% "vue/dist/vue.esm-bundler.js=vue"</info>

Sometimes, a package may require other packages and multiple new items may be added
to the import map.

You can also require multiple packages at once:

    <info>php %command.full_name% "lodash@^4.15" "@hotwired/stimulus"</info>

To add an importmap entry pointing to a local file, use the <info>path</info> option:

    <info>php %command.full_name% "any_module_name" --path=./assets/some_file.js</info>

To simulate the installation, use the <info>--dry-run</info> option:

    <info>php %command.full_name% "any_module_name" --dry-run -v</info>

When this option is enabled, this command does not perform any write operations to the filesystem.
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
        }

        if ($input->getOption('dry-run')) {
            $io->writeln(['', '<comment>[DRY-RUN]</comment> No changes will apply to the importmap configuration.', '']);
        }

        $packages = [];
        foreach ($packageList as $packageName) {
            $parts = ImportMapManager::parsePackageName($packageName);
            if (null === $parts) {
                $io->error(\sprintf('Package "%s" is not a valid package name format. Use the format PACKAGE@VERSION - e.g. "lodash" or "lodash@^4"', $packageName));

                return Command::FAILURE;
            }

            $packages[] = new PackageRequireOptions(
                $parts['package'],
                $parts['version'] ?? null,
                $parts['alias'] ?? null,
                $path,
                $input->getOption('entrypoint'),
            );
        }

        if ($input->getOption('dry-run')) {
            $newPackages = $this->importMapManager->requirePackages($packages, new ImportMapEntries());
        } else {
            $newPackages = $this->importMapManager->require($packages);
        }

        $this->renderVersionProblems($this->importMapVersionChecker, $output);

        $newPackageNames = array_map(fn (ImportMapEntry $package): string => $package->importName, $newPackages);

        if (1 === \count($newPackages)) {
            $messages = [\sprintf('Package "%s" added to importmap.php.', $newPackageNames[0])];
        } else {
            $messages = [\sprintf('%d new items (%s) added to the importmap.php!', \count($newPackages), implode(', ', $newPackageNames))];
        }

        if ($io->isVerbose()) {
            $io->table(
                ['Package', 'Version', 'Path'],
                array_map(fn (ImportMapEntry $package): array => [
                    $package->importName,
                    $package->version ?? '-',
                    // BC layer for AssetMapper < 7.3
                    // When `projectDir` is not null, we use the absolute path of the package
                    null !== $this->projectDir ? Path::makeRelative($package->path, $this->projectDir) : $package->path,
                ], $newPackages),
            );
        }

        if (1 === \count($newPackages)) {
            $messages[] = \sprintf('Use the new package normally by importing "%s".', $newPackages[0]->importName);
        }

        $io->success($messages);

        if ($input->getOption('dry-run')) {
            $io->writeln(['<comment>[DRY-RUN]</comment> No changes applied to the importmap configuration.', '']);
        }

        return Command::SUCCESS;
    }
}
