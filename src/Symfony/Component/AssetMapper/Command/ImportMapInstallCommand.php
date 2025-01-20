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

use Symfony\Component\AssetMapper\ImportMap\RemotePackageDownloader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Downloads all assets that should be downloaded.
 *
 * @author Jonathan Scheiber <contact@jmsche.fr>
 */
#[AsCommand(name: 'importmap:install', description: 'Download all assets that should be downloaded')]
final class ImportMapInstallCommand extends Command
{
    public function __construct(
        private readonly RemotePackageDownloader $packageDownloader,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $finishedCount = 0;
        $progressBar = new ProgressBar($output);
        $progressBar->setFormat('<info>%current%/%max%</info> %bar% %url%');
        $downloadedPackages = $this->packageDownloader->downloadPackages(function (string $package, string $event, ResponseInterface $response, int $totalPackages) use (&$finishedCount, $progressBar) {
            $progressBar->setMessage($response->getInfo('url'), 'url');
            if (0 === $progressBar->getMaxSteps()) {
                $progressBar->setMaxSteps($totalPackages);
                $progressBar->start();
            }

            if ('finished' === $event) {
                ++$finishedCount;
                $progressBar->advance();
            }
        });
        $progressBar->finish();
        $progressBar->clear();

        if (!$downloadedPackages) {
            $io->success('No assets to install.');

            return Command::SUCCESS;
        }

        $io->success(\sprintf(
            'Downloaded %d package%s into %s.',
            \count($downloadedPackages),
            1 === \count($downloadedPackages) ? '' : 's',
            str_replace($this->projectDir.'/', '', $this->packageDownloader->getVendorDir()),
        ));

        return Command::SUCCESS;
    }
}
