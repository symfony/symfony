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

use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageResolverInterface;
use Symfony\Component\AssetMapper\ImportMap\Resolver\PackageUrlResolverInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'importmap:export-remote', description: 'Export remote packages as HTML')]
final class ImportMapExportRemoteCommand extends Command
{
    public function __construct(
        private readonly ImportMapConfigReader $importMapConfigReader,
        private readonly PackageResolverInterface $packageResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'EOT'
            The <info>%command.name%</info> command prints remote packages from <comment>importmap.php</comment> as HTML.
            Copy the output into the head of another page. Local files aren't included.

                <info>php %command.full_name%</info>
            EOT);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $imports = [];
        $stylesheets = [];

        foreach ($this->importMapConfigReader->getEntries() as $entry) {
            if (!$entry->isRemotePackage()) {
                continue;
            }

            if (ImportMapType::JSON === $entry->type) {
                throw new \InvalidArgumentException(\sprintf('The remote JSON entry "%s" cannot be exported.', $entry->importName));
            }

            if (!$this->packageResolver instanceof PackageUrlResolverInterface) {
                throw new \LogicException(\sprintf('The configured package resolver must implement "%s" to export URLs.', PackageUrlResolverInterface::class));
            }

            $url = $this->packageResolver->getPackageUrl($entry);
            if (ImportMapType::CSS === $entry->type) {
                $stylesheets[$url] = $url;
                $imports[$entry->importName] = 'data:application/javascript,';
            } else {
                $imports[$entry->importName] = $url;
            }
        }

        uksort($imports, strcmp(...));
        sort($stylesheets, \SORT_STRING);

        foreach ($stylesheets as $url) {
            $output->writeln('<link rel="stylesheet" href="'.htmlspecialchars($url, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'">', OutputInterface::OUTPUT_RAW);
        }

        $output->writeln('<script type="importmap">', OutputInterface::OUTPUT_RAW);
        $output->writeln(json_encode(['imports' => $imports ?: new \stdClass()], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_HEX_TAG), OutputInterface::OUTPUT_RAW);
        $output->writeln('</script>', OutputInterface::OUTPUT_RAW);

        return Command::SUCCESS;
    }
}
