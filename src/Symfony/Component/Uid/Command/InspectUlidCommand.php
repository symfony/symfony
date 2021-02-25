<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Uid\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;

class InspectUlidCommand extends Command
{
    protected static $defaultName = 'ulid:inspect';
    protected static $defaultDescription = 'Inspects a ULID';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('ulid', InputArgument::REQUIRED, 'The ULID to inspect'),
            ])
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> displays information about a ULID.

    <info>php %command.full_name% 01EWAKBCMWQ2C94EXNN60ZBS0Q</info>
    <info>php %command.full_name% 1BVdfLn3ERmbjYBLCdaaLW</info>
    <info>php %command.full_name% 01771535-b29c-b898-923b-b5a981f5e417</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        try {
            $ulid = Ulid::fromString($input->getArgument('ulid'));
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return 1;
        }

        $io->table(['Label', 'Value'], [
            ['Canonical (Base 32)', (string) $ulid],
            ['Base 58', $ulid->toBase58()],
            ['RFC 4122', $ulid->toRfc4122()],
            new TableSeparator(),
            ['Timestamp', ($ulid->getDateTime())->format('Y-m-d H:i:s.v')],
        ]);

        return 0;
    }
}
