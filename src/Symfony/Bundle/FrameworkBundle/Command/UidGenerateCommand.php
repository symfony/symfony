<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;

class UidGenerateCommand extends Command
{
    protected static $defaultName = 'uid:generate';

    private const UUID_1 = '1';
    private const UUID_3 = '3';
    private const UUID_4 = '4';
    private const UUID_5 = '5';
    private const UUID_6 = '6';
    private const ULID = 'ulid';

    private static $types = [
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
        $typesAsString = implode(', ', self::$types);

        $this
            ->setDefinition([
                new InputArgument('type', InputArgument::OPTIONAL, 'The type/version of the generated UID.', null),
                new InputArgument('namespace', InputArgument::OPTIONAL, 'Namespace for UUID V3 and V5 versions.', null),
                new InputArgument('name', InputArgument::OPTIONAL, 'Name for UUID V3 and V5 versions.', null),
            ])
            ->setDescription('Generates a UID, that can be either a ULID or a UUID in a given version.')
            ->setHelp(<<<EOF
The <info>%command.name%</info> generates UID. This can be a ULID or a UUID
in a given version. Available types are $typesAsString.
Examples:

  <info>php %command.full_name% ulid</info> for generating a ULID.
  <info>php %command.full_name% 1</info> for generating a UUID in version 1.
  <info>php %command.full_name% 3 9b7541de-6f87-11ea-ab3c-9da9a81562fc foo</info> for generating a UUID in version 3.

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
        $io = new SymfonyStyle($input, $output);

        $type = $input->getArgument('type') ? strtolower($input->getArgument('type')) : null;
        $namespace = $input->getArgument('namespace');
        $name = $input->getArgument('name');

        if (null === $type || !\in_array($type, self::$types, true)) {
            $type = $io->ask('Type/version of the UID. Available values are '.implode(', ', self::$types).'.', null, function ($type) {
                $type = \strtolower($type);
                if (!\in_array($type, self::$types, true)) {
                    throw new \RuntimeException('Available values are '.implode(', ', self::$types).'.');
                }

                return $type;
            });
        }

        if (\in_array($type, [self::UUID_3, self::UUID_5], true) && (null === $namespace || !Uuid::isValid($namespace))) {
            $namespace = $io->ask('Please enter a valid namespace:', null, function($namespace) {
                if (null === $namespace || !Uuid::isValid($namespace)) {
                    throw new \RuntimeException('This is not a valid namespace');
                }

                return $namespace;
            });
        }

        if (\in_array($type, [self::UUID_3, self::UUID_5], true) && empty($name)) {
            $name = $io->ask('Please enter a name. Press Enter for an empty string. ', '');
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
        }

        $io->title('Generated UID:');
        $io->text($uid);

        return 0;
    }
}
