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
use Symfony\Component\Console\Helper\Dumper;
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
#[AsCommand(name: 'secrets:list', description: 'List all secrets')]
final class SecretsListCommand extends Command
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
            ->addOption('reveal', 'r', InputOption::VALUE_NONE, 'Display decrypted values alongside names')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command list all stored secrets.

    <info>%command.full_name%</info>

When the option <info>--reveal</info> is provided, the decrypted secrets are also displayed.

    <info>%command.full_name% --reveal</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        $io->comment('Use <info>"%env(<name>)%"</info> to reference a secret in a config file.');

        if (!$reveal = $input->getOption('reveal')) {
            $io->comment(sprintf('To reveal the secrets run <info>php %s %s --reveal</info>', $_SERVER['PHP_SELF'], $this->getName()));
        }

        $secrets = $this->vault->list($reveal);
        $localSecrets = $this->localVault?->list($reveal);

        $rows = [];

        $dump = new Dumper($output);
        $dump = fn ($v) => null === $v ? '******' : $dump($v);

        foreach ($secrets as $name => $value) {
            $rows[$name] = [$name, $dump($value)];
        }

        if (null !== $message = $this->vault->getLastMessage()) {
            $io->comment($message);
        }

        foreach ($localSecrets ?? [] as $name => $value) {
            if (isset($rows[$name])) {
                $rows[$name][] = $dump($value);
            }
        }

        if (null !== $this->localVault && null !== $message = $this->localVault->getLastMessage()) {
            $io->comment($message);
        }

        (new SymfonyStyle($input, $output))
            ->table(['Secret', 'Value'] + (null !== $localSecrets ? [2 => 'Local Value'] : []), $rows);

        $io->comment("Local values override secret values.\nUse <info>secrets:set --local</info> to define them.");

        return 0;
    }
}
