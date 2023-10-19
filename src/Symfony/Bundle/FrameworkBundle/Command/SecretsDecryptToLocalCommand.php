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
#[AsCommand(name: 'secrets:decrypt-to-local', description: 'Decrypt all secrets and stores them in the local vault')]
final class SecretsDecryptToLocalCommand extends Command
{
    private AbstractVault $vault;
    private ?AbstractVault $localVault;

    public function __construct(AbstractVault $vault, AbstractVault $localVault = null)
    {
        $this->vault = $vault;
        $this->localVault = $localVault;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force overriding of secrets that already exist in the local vault')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command decrypts all secrets and copies them in the local vault.

    <info>%command.full_name%</info>

When the option <info>--force</info> is provided, secrets that already exist in the local vault are overriden.

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

        $secrets = $this->vault->list(true);

        $io->comment(sprintf('%d secret%s found in the vault.', \count($secrets), 1 !== \count($secrets) ? 's' : ''));

        $skipped = 0;
        if (!$input->getOption('force')) {
            foreach ($this->localVault->list() as $k => $v) {
                if (isset($secrets[$k])) {
                    ++$skipped;
                    unset($secrets[$k]);
                }
            }
        }

        if ($skipped > 0) {
            $io->warning([
                sprintf('%d secret%s already overridden in the local vault and will be skipped.', $skipped, 1 !== $skipped ? 's are' : ' is'),
                'Use the --force flag to override these.',
            ]);
        }

        foreach ($secrets as $k => $v) {
            if (null === $v) {
                $io->error($this->vault->getLastMessage() ?? sprintf('Secret "%s" has been skipped as there was an error reading it.', $k));
                continue;
            }

            $this->localVault->seal($k, $v);
            $io->note($this->localVault->getLastMessage());
        }

        return 0;
    }
}
