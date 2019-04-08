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

use Symfony\Bundle\FrameworkBundle\Exception\EncryptionKeyNotFoundException;
use Symfony\Bundle\FrameworkBundle\Secret\Storage\SecretStorageInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
final class SecretsListCommand extends Command
{
    protected static $defaultName = 'debug:secrets';

    private $secretStorage;

    public function __construct(SecretStorageInterface $secretStorage)
    {
        $this->secretStorage = $secretStorage;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('reveal', 'r', InputOption::VALUE_NONE, 'Display decrypted values alongside names'),
            ])
            ->setDescription('Lists all secrets.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command list all stored secrets.

    %command.full_name%

When the the option <info>--reveal</info> is provided, the decrypted secrets are also displayed. 

    %command.full_name% --reveal
EOF
            )
        ;

        $this
            ->setDescription('Lists all secrets.')
            ->addOption('reveal', 'r', InputOption::VALUE_NONE, 'Display decrypted values alongside names');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $reveal = $input->getOption('reveal');
        $io = new SymfonyStyle($input, $output);

        try {
            $secrets = $this->secretStorage->listSecrets($reveal);
        } catch (EncryptionKeyNotFoundException $e) {
            throw new \LogicException(sprintf('Unable to decrypt secrets, the encryption key "%s" is missing.', $e->getKeyLocation()));
        }

        if ($reveal) {
            $rows = [];
            foreach ($secrets as $name => $value) {
                $rows[] = [$name, $value];
            }
            $io->table(['name', 'secret'], $rows);

            return;
        }

        $rows = [];
        foreach ($secrets as $name => $_) {
            $rows[] = [$name];
        }

        $io->comment(sprintf('To reveal the values of the secrets use <info>php %s %s --reveal</info>', $_SERVER['PHP_SELF'], $this->getName()));
        $io->table(['name'], $rows);
    }
}
