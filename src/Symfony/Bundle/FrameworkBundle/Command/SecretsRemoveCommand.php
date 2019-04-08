<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Bundle\FrameworkBundle\Secret\Storage\MutableSecretStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class SecretsRemoveCommand extends Command
{
    protected static $defaultName = 'secrets:remove';

    private $secretsStorage;

    public function __construct(MutableSecretStorageInterface $secretsStorage)
    {
        $this->secretsStorage = $secretsStorage;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'The name of the secret'),
            ])
            ->setDescription('Removes a secret from the storage.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command remove a secret.

    %command.full_name% <name>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $this->secretsStorage->removeSecret($input->getArgument('name'));

        $io->success('Secret was successfully removed.');
    }
}
