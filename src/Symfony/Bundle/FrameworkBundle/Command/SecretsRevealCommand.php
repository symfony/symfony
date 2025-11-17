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
#[AsCommand(name: 'secrets:reveal', description: 'Reveal the value of a secret')]
final class SecretsRevealCommand extends Command
{
    public function __construct(
        private readonly AbstractVault $vault,
        private readonly ?AbstractVault $localVault = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the secret to reveal', null, fn () => array_keys($this->vault->list()))
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command reveals a stored secret.

                    <info>%command.full_name%</info>
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $secrets = $this->vault->list(true);
        $localSecrets = $this->localVault?->list(true);

        $name = (string) $input->getArgument('name');

        if (null !== $localSecrets && \array_key_exists($name, $localSecrets)) {
            $io->writeln($localSecrets[$name]);
        } else {
            if (!\array_key_exists($name, $secrets)) {
                $io->error(\sprintf('The secret "%s" does not exist.', $name));

                return self::INVALID;
            } elseif (null === $secrets[$name]) {
                $io->error(\sprintf('The secret "%s" could not be decrypted.', $name));

                return self::INVALID;
            }

            $io->writeln($secrets[$name]);
        }

        return self::SUCCESS;
    }
}
