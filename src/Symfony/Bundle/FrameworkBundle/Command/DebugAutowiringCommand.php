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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command for autowiring information.
 *
 * @author Ryan Weaver <ryan@knpuniversity.com>
 *
 * @internal
 */
class DebugAutowiringCommand extends ContainerDebugCommand
{
    protected static $defaultName = 'debug:autowiring';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('search', InputArgument::OPTIONAL, 'A search filter'),
            ))
            ->setDescription('Lists classes/interfaces you can use for autowiring')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all classes and interfaces that
you can use as type-hints for autowiring:

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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $builder = $this->getContainerBuilder();
        $serviceIds = $builder->getServiceIds();
        $serviceIds = array_filter($serviceIds, array($this, 'filterToServiceTypes'));

        if ($search = $input->getArgument('search')) {
            $serviceIds = array_filter($serviceIds, function ($serviceId) use ($search) {
                return false !== stripos(str_replace('\\', '', $serviceId), $search) && 0 !== strpos($serviceId, '.');
            });

            if (empty($serviceIds)) {
                $errorIo->error(sprintf('No autowirable classes or interfaces found matching "%s"', $search));

                return 1;
            }
        }

        asort($serviceIds);

        $io->title('Autowirable Services');
        $io->text('The following classes & interfaces can be used as type-hints when autowiring:');
        if ($search) {
            $io->text(sprintf('(only showing classes/interfaces matching <comment>%s</comment>)', $search));
        }
        $io->newLine();
        $tableRows = array();
        $hasAlias = array();
        foreach ($serviceIds as $serviceId) {
            if ($builder->hasAlias($serviceId)) {
                $tableRows[] = array(sprintf('<fg=cyan>%s</fg=cyan>', $serviceId));
                $tableRows[] = array(sprintf('    alias to %s', $builder->getAlias($serviceId)));
                $hasAlias[(string) $builder->getAlias($serviceId)] = true;
            } else {
                $tableRows[$serviceId] = array(sprintf('<fg=cyan>%s</fg=cyan>', $serviceId));
            }
        }

        $io->table(array(), array_diff_key($tableRows, $hasAlias));
    }
}
