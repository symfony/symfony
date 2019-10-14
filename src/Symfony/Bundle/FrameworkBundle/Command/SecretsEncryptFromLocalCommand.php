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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
final class SecretsEncryptFromLocalCommand extends Command
{
    protected static $defaultName = 'secrets:encrypt-from-local';

    private $vault;
    private $localVault;

    public function __construct(AbstractVault $vault, AbstractVault $localVault = null)
    {
        $this->vault = $vault;
        $this->localVault = $localVault;

        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Encrypts all local secrets to the vault.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces overriding of secrets that already exist in the vault')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command list encrypts all local secrets and stores them in the vault..

    <info>%command.full_name%</info>

When the option <info>--force</info> is provided, secrets that already exist in the vault are overriden.

    <info>%command.full_name% --force</info>
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

        $secrets = $this->localVault->list(true);

        if (!$input->getOption('force')) {
            foreach ($this->vault->list() as $k => $v) {
                unset($secrets[$k]);
            }
        }

        foreach ($secrets as $k => $v) {
            if (null === $v) {
                $io->error($this->localVault->getLastMessage());

                return 1;
            }

            $this->vault->seal($k, $v);
        }

        return 0;
    }
}
