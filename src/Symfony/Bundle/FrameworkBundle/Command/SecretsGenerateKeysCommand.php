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
 * @author Tobias Schultze <http://tobion.de>
 * @author Jérémy Derussé <jeremy@derusse.com>
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
#[AsCommand(name: 'secrets:generate-keys', description: 'Generate new encryption keys')]
final class SecretsGenerateKeysCommand extends Command
{
    public function __construct(
        private AbstractVault $vault,
        private ?AbstractVault $localVault = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('local', 'l', InputOption::VALUE_NONE, 'Update the local vault.')
            ->addOption('rotate', 'r', InputOption::VALUE_NONE, 'Re-encrypt existing secrets with the newly generated keys.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command generates a new encryption key.

    <info>%command.full_name%</info>

If encryption keys already exist, the command must be called with
the <info>--rotate</info> option in order to override those keys and re-encrypt
existing secrets.

    <info>%command.full_name% --rotate</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        $vault = $input->getOption('local') ? $this->localVault : $this->vault;

        if (null === $vault) {
            $io->success('The local vault is disabled.');

            return 1;
        }

        if (!$input->getOption('rotate')) {
            if ($vault->generateKeys()) {
                $io->success($vault->getLastMessage());

                if ($this->vault === $vault) {
                    $io->caution('DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT⚠️');
                }

                return 0;
            }

            $io->warning($vault->getLastMessage());

            return 1;
        }

        $secrets = [];
        foreach ($vault->list(true) as $name => $value) {
            if (null === $value) {
                $io->error($vault->getLastMessage());

                return 1;
            }

            $secrets[$name] = $value;
        }

        if (!$vault->generateKeys(true)) {
            $io->warning($vault->getLastMessage());

            return 1;
        }

        $io->success($vault->getLastMessage());

        if ($secrets) {
            foreach ($secrets as $name => $value) {
                $vault->seal($name, $value);
            }

            $io->comment('Existing secrets have been rotated to the new keys.');
        }

        if ($this->vault === $vault) {
            $io->caution('DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT⚠️');
        }

        return 0;
    }
}
