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

use Symfony\Bundle\FrameworkBundle\Secrets\AbstractVault;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[AsCommand(name: 'secrets:import-env-file', description: 'Import multiple secrets from an env file into the vault')]
final class SecretsImportEnvFileCommand extends Command
{
    public function __construct(
        private AbstractVault $vault,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'The env file where to read the secrets from.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command import secrets from an env file into the vault.

    <info>%command.full_name% <name></info>

Secrets with null values will be ignored.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $io = new SymfonyStyle($input, $errOutput);
        $file = $input->getArgument('file');

        if (!is_file($file)) {
            throw new \InvalidArgumentException(sprintf('File not found: "%s".', $file));
        } elseif (!is_readable($file)) {
            throw new \InvalidArgumentException(sprintf('File is not readable: "%s".', $file));
        }

        if ($this->vault->generateKeys()) {
            $io->success($this->vault->getLastMessage());
        }

        $secrets = [];
        foreach(file($file, FILE_SKIP_EMPTY_LINES|FILE_IGNORE_NEW_LINES) as $line) {
            if(str_starts_with(trim($line), '#')){
                continue;
            }
            $exploded = explode('=', $line, 2);
            if('' === ($exploded[1]?? '')){
                continue;
            }
            [$name, $value] = $exploded;
            $secrets[$name] = trim(trim( $value,'"'),"'");
        }

        foreach ($secrets as $name => $value){
            $this->vault->seal($name, $value);
        }

        $io->success('Secrets were successfully stored in the vault.');

        return 0;
    }
}
