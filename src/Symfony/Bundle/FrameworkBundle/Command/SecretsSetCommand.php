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
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
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
#[AsCommand(name: 'secrets:set', description: 'Set a secret in the vault')]
final class SecretsSetCommand extends Command
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
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the secret')
            ->addArgument('file', InputArgument::OPTIONAL, 'A file where to read the secret from or "-" for reading from STDIN')
            ->addOption('local', 'l', InputOption::VALUE_NONE, 'Update the local vault.')
            ->addOption('random', 'r', InputOption::VALUE_OPTIONAL, 'Generate a random value.', false)
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command stores a secret in the vault.

                    <info>%command.full_name% <name></info>

                To reference secrets in services.yaml or any other config
                files, use <info>"%env(<name>)%"</info>.

                By default, the secret value should be entered interactively.
                Alternatively, provide a file where to read the secret from:

                    <info>php %command.full_name% <name> filename</info>

                Use "-" as a file name to read from STDIN:

                    <info>cat filename | php %command.full_name% <name> -</info>

                Use <info>--local</info> to override secrets for local needs.
                EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $errOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $io = new SymfonyStyle($input, $errOutput);
        $name = $input->getArgument('name');
        $vault = $input->getOption('local') ? $this->localVault : $this->vault;

        if (null === $vault) {
            $io->error('The local vault is disabled.');

            return 1;
        }

        if ($this->localVault === $vault && !\array_key_exists($name, $this->vault->list())) {
            $io->error(\sprintf('Secret "%s" does not exist in the vault, you cannot override it locally.', $name));

            return 1;
        }

        if (0 < $random = $input->getOption('random') ?? 16) {
            $value = strtr(substr(base64_encode(random_bytes($random)), 0, $random), '+/', '-_');
        } elseif (!$file = $input->getArgument('file')) {
            $value = $io->askHidden('Please type the secret value');

            if (null === $value) {
                $io->warning('No value provided: using empty string');
                $value = '';
            }
        } elseif ('-' === $file) {
            $value = file_get_contents('php://stdin');
        } elseif (is_file($file) && is_readable($file)) {
            $value = file_get_contents($file);
        } elseif (!is_file($file)) {
            throw new \InvalidArgumentException(\sprintf('File not found: "%s".', $file));
        } elseif (!is_readable($file)) {
            throw new \InvalidArgumentException(\sprintf('File is not readable: "%s".', $file));
        }

        if ($vault->generateKeys()) {
            $io->success($vault->getLastMessage());

            if ($this->vault === $vault) {
                $io->caution('DO NOT COMMIT THE DECRYPTION KEY FOR THE PROD ENVIRONMENT⚠️');
            }
        }

        $vault->seal($name, $value);

        $io->success($vault->getLastMessage() ?? 'Secret was successfully stored in the vault.');

        if (0 < $random) {
            $errOutput->write(' // The generated random value is: <comment>');
            $output->write($value);
            $errOutput->writeln('</comment>');
            $io->newLine();
        }

        if ($this->vault === $vault && null !== $this->localVault->reveal($name)) {
            $io->comment('Note that this secret is overridden in the local vault.');
        }

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys($this->vault->list(false)));
        }
    }
}
