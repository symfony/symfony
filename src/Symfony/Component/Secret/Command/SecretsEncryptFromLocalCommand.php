<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\Secret\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Secret\AbstractVault;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class SecretsEncryptFromLocalCommand extends Command
{
    protected static $defaultName = 'encrypt-from-local';

    private $vault;
    private $localVault;

    public function __construct(AbstractVault $vault, AbstractVault $localVault = null)
    {
        $this->vault = $vault;
        $this->localVault = $localVault;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Encrypt all local secrets to the vault')
            ->addOption('dir', 'd', InputOption::VALUE_REQUIRED, 'Target directory')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command encrypts all locally overridden secrets to the vault.

    <info>%command.full_name%</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if (null === $this->localVault) {
            $io->error('The local vault is disabled.');

            return 1;
        }

        foreach ($this->vault->list(true) as $name => $value) {
            $localValue = $this->localVault->reveal($name);

            if (null !== $localValue && $value !== $localValue) {
                $this->vault->seal($name, $localValue);
            } elseif (null !== $message = $this->localVault->getLastMessage()) {
                $io->error($message);

                return 1;
            }
        }

        return 0;
    }
}
