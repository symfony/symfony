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

use Symfony\Component\AssetMapper\ImportMap\ImportMapUpdateChecker;
use Symfony\Component\AssetMapper\ImportMap\PackageUpdateInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'importmap:outdated', description: 'List outdated JavaScript packages and their latest versions')]
final class ImportMapOutdatedCommand extends Command
{
    private const COLOR_MAPPING = [
        'update-possible' => 'yellow',
        'semver-safe-update' => 'red',
    ];

    public function __construct(
        private readonly ImportMapUpdateChecker $updateChecker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                name: 'packages',
                mode: InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                description: 'A list of packages to check',
            )
            ->addOption(
                name: 'format',
                mode: InputOption::VALUE_REQUIRED,
                description: sprintf('The output format ("%s")', implode(', ', $this->getAvailableFormatOptions())),
                default: 'txt',
            )
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command will list the latest updates available for the 3rd party packages in <comment>importmap.php</comment>.
Versions showing in <fg=red>red</> are semver compatible versions and you should upgrading.
Versions showing in <fg=yellow>yellow</> are major updates that include backward compatibility breaks according to semver.

   <info>php %command.full_name%</info>

Or specific packages only:

   <info>php %command.full_name% <packages></info>
EOT
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $packages = $input->getArgument('packages');
        $packagesUpdateInfos = $this->updateChecker->getAvailableUpdates($packages);
        $packagesUpdateInfos = array_filter($packagesUpdateInfos, fn ($packageUpdateInfo) => $packageUpdateInfo->hasUpdate());
        if (0 === \count($packagesUpdateInfos)) {
            return Command::SUCCESS;
        }

        $displayData = array_map(fn (string $importName, PackageUpdateInfo $packageUpdateInfo) => [
            'name' => $importName,
            'current' => $packageUpdateInfo->currentVersion,
            'latest' => $packageUpdateInfo->latestVersion,
            'latest-status' => PackageUpdateInfo::UPDATE_TYPE_MAJOR === $packageUpdateInfo->updateType ? 'update-possible' : 'semver-safe-update',
        ], array_keys($packagesUpdateInfos), $packagesUpdateInfos);

        if ('json' === $input->getOption('format')) {
            $io->writeln(json_encode($displayData, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        } else {
            $table = $io->createTable();
            $table->setHeaders(['Package', 'Current', 'Latest']);
            foreach ($displayData as $datum) {
                $color = self::COLOR_MAPPING[$datum['latest-status']] ?? 'default';
                $table->addRow([
                    sprintf('<fg=%s>%s</>', $color, $datum['name']),
                    $datum['current'],
                    sprintf('<fg=%s>%s</>', $color, $datum['latest']),
                ]);
            }
            $table->render();
        }

        return Command::FAILURE;
    }

    private function getAvailableFormatOptions(): array
    {
        return ['txt', 'json'];
    }
}
