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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\YamlDumper;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\AbstractDumper;
use Symfony\Component\VarDumper\Dumper\CliDumper;

/**
 * A console command for autoconfiguration information.
 *
 * @internal
 */
final class DebugAutoconfigurationCommand extends ContainerDebugCommand
{
    protected static $defaultName = 'debug:autoconfiguration';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('search', InputArgument::OPTIONAL, 'A search filter'),
                new InputOption('tags', null, InputOption::VALUE_NONE, 'Displays autoconfiguration interfaces/class grouped by tags'),
            ])
            ->setDescription('Displays current autoconfiguration for an application')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all services that are autoconfigured:

  <info>php %command.full_name%</info>

You can also pass a search term to filter the list:

  <info>php %command.full_name% log</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $definitions = $this->getContainerBuilder()->getAutoconfiguredInstanceof();
        ksort($definitions, SORT_NATURAL);

        if ($search = $input->getArgument('search')) {
            $definitions = array_filter($definitions, function ($key) use ($search) {
                return false !== stripos(str_replace('\\', '', $key), $search);
            }, ARRAY_FILTER_USE_KEY);

            if (0 === \count($definitions)) {
                $errorIo->error(sprintf('No autoconfiguration interface/class found matching "%s"', $search));

                return 1;
            }

            $name = $this->findProperInterfaceName(array_keys($definitions), $input, $io, $search);
            /** @var ChildDefinition $definition */
            $definition = $definitions[$name];

            $io->title(sprintf('Information for Interface/Class "<info>%s</info>"', $name));
            $tableHeaders = ['Option', 'Value'];
            $tableRows = [];

            $tagInformation = [];
            foreach ($definition->getTags() as $tagName => $tagData) {
                foreach ($tagData as $tagParameters) {
                    $parameters = array_map(function ($key, $value) {
                        return sprintf('<info>%s</info>: %s', $key, $value);
                    }, array_keys($tagParameters), array_values($tagParameters));
                    $parameters = implode(', ', $parameters);

                    if ('' === $parameters) {
                        $tagInformation[] = sprintf('%s', $tagName);
                    } else {
                        $tagInformation[] = sprintf('%s (%s)', $tagName, $parameters);
                    }
                }
            }
            $tableRows[] = ['Tags', implode("\n", $tagInformation)];

            $calls = $definition->getMethodCalls();
            if (\count($calls) > 0) {
                $callInformation = [];
                foreach ($calls as $call) {
                    $callInformation[] = $call[0];
                }
                $tableRows[] = ['Calls', implode(', ', $callInformation)];
            }

            $io->table($tableHeaders, $tableRows);
        } else {
            $io->table(['Interface/Class'], array_map(static function ($interface) {
                return [$interface];
            }, array_keys($definitions)));
        }

        $io->newLine();

        return 0;
    }

    private function findProperInterfaceName(array $list, InputInterface $input, SymfonyStyle $io, string $name): string
    {
        $name = ltrim($name, '\\');

        if (\in_array($name, $list, true)) {
            return $name;
        }

        if (1 === \count($list)) {
            return $list[0];
        }

        return $io->choice('Select one of the following interfaces to display its information', $list);
    }
}
