<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * HelpCommand displays the help for a given command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HelpCommand extends Command
{
    private Command $command;

    protected function configure(): void
    {
        $this->ignoreValidationErrors();

        $this
            ->setName('help')
            ->setDefinition([
                new InputArgument('command_name', InputArgument::OPTIONAL, 'The command name', 'help', fn () => array_keys((new ApplicationDescription($this->getApplication()))->getCommands())),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt', static fn () => (new DescriptorHelper())->getFormats()),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw command help'),
                new InputOption('show-hidden-options', null, InputOption::VALUE_NONE | InputOption::HIDDEN, 'Show hidden options'),
            ])
            ->setDescription('Display help for a command')
            ->setHelp(<<<'EOF'
                The <info>%command.name%</info> command displays help for a given command:

                  <info>%command.full_name% list</info>

                You can also output the help in other formats by using the <info>--format</info> option:

                  <info>%command.full_name% --format=xml list</info>

                To display the list of available commands, please use the <info>list</info> command.
                EOF
            )
        ;

        $this->getDefinition()->setIgnoreExtraArguments();
    }

    public function setCommand(Command $command): void
    {
        $this->command = $command;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!isset($this->command)) {
            $application = $this->getApplication();
            $name = $input->getArgument('command_name');

            if ($input instanceof ArgvInput || $input instanceof ArrayInput) {
                // a spaced path names a command in a tree: "help docker compose"
                foreach ($input->getUnparsedTokens() as $token) {
                    $childPath = ($application->has($name) ? $application->get($name)->getName() : $name).':'.$token;
                    if (!$application->has($childPath) && !\in_array($childPath, $application->getNamespaces(), true)) {
                        break;
                    }
                    $name = $childPath;
                }
            }

            if (!$application->has($name) && \in_array($name, $application->getNamespaces(), true)) {
                $this->command = new Command($name);
                $this->command->setApplication($application);
            } else {
                $this->command = $application->find($name);
            }
        }

        $helper = new DescriptorHelper();
        $helper->describe($output, $this->command, [
            'format' => $input->getOption('format'),
            'raw_text' => $input->getOption('raw'),
            'show-hidden-options' => $input->getOption('show-hidden-options'),
        ]);

        unset($this->command);

        return 0;
    }
}
