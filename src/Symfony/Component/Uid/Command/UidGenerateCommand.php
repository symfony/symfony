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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class UidGenerateCommand extends Command
{
    protected static $defaultName = 'uid:generate';

    private const UUID_1 = 'uuid-1';
    private const UUID_3 = 'uuid-3';
    private const UUID_4 = 'uuid-4';
    private const UUID_5 = 'uuid-5';
    private const UUID_6 = 'uuid-6';
    private const ULID = 'ulid';

    private const TYPES = [
        self::UUID_1,
        self::UUID_3,
        self::UUID_4,
        self::UUID_5,
        self::UUID_6,
        self::ULID,
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $typesAsString = implode(', ', self::TYPES);

        $this
            ->setDefinition([
                new InputArgument('type', InputArgument::REQUIRED, 'The type/version of the generated UID.'),
                new InputArgument('namespace', InputArgument::OPTIONAL, 'Namespace for UUID V3 and V5 versions.', ''),
                new InputArgument('name', InputArgument::OPTIONAL, 'Name for UUID V3 and V5 versions.', ''),
                new InputOption('base32', null, InputOption::VALUE_NONE, 'Use this option to represent the generated UUID/ULID in base 32.'),
                new InputOption('base58', null, InputOption::VALUE_NONE, 'Use this option to represent the generated UUID/ULID in base 58.'),
            ])
            ->setDescription('Generates a UID, that can be either a ULID or a UUID in a given version.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> generates UID. This can be a ULID or a UUID
in a given version. Available types are $typesAsString.
Examples:

  <info>php %command.full_name% ulid</info> for generating a ULID.
  <info>php %command.full_name% uuid-1</info> for generating a UUID in version 1.
  <info>php %command.full_name% uuid-3 9b7541de-6f87-11ea-ab3c-9da9a81562fc foo</info> for generating a UUID in version 3.
  <info>php %command.full_name% uuid-4 --base32</info> for generating a UUID in version 4 represented in base 32.

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $namespace = $input->getArgument('namespace');
        $name = $input->getArgument('name');

        if (\in_array($type, [self::UUID_3, self::UUID_5], true) && !Uuid::isValid($namespace)) {
            throw new InvalidArgumentException('You must specify a valid namespace as a second argument.');
        }

        switch ($type) {
            case self::UUID_1:
                $uid = Uuid::v1();
                break;
            case self::UUID_3:
                $uid = Uuid::v3(Uuid::fromString($namespace), $name);
                break;
            case self::UUID_4:
                $uid = Uuid::v4();
                break;
            case self::UUID_5:
                $uid = Uuid::v5(Uuid::fromString($namespace), $name);
                break;
            case self::UUID_6:
                $uid = Uuid::v6();
                break;
            case self::ULID:
                $uid = new Ulid();
                break;
            default:
                throw new InvalidArgumentException('Invalaible UID type. Available values are '.implode(', ', self::TYPES).'.');
                break;
        }

        if ($input->getOption('base32')) {
            $uid = $uid->toBase32();
        }

        if ($input->getOption('base58')) {
            $uid = $uid->toBase58();
        }

        $output->writeln($uid);

        return 0;
    }
}
