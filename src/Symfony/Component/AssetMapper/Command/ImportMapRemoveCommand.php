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

use Symfony\Component\AssetMapper\ImportMap\ImportMapManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Kévin Dunglas <kevin@dunglas.dev>
 */
#[AsCommand(name: 'importmap:remove', description: 'Remove JavaScript packages')]
final class ImportMapRemoveCommand extends Command
{
    public function __construct(
        protected readonly ImportMapManager $importMapManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('packages', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'The packages to remove')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command removes packages from the <comment>importmap.php</comment>.
If a package was downloaded into your app, the downloaded file will also be removed.

For example:

    <info>php %command.full_name% lodash</info>
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $packageList = $input->getArgument('packages');
        $this->importMapManager->remove($packageList);

        if (1 === \count($packageList)) {
            $io->success(\sprintf('Removed "%s" from importmap.php.', $packageList[0]));
        } else {
            $io->success(\sprintf('Removed %d items from importmap.php.', \count($packageList)));
        }

        return Command::SUCCESS;
    }
}
