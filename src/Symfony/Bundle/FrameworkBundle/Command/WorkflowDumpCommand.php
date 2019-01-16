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
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Workflow\Dumper\GraphvizDumper;
use Symfony\Component\Workflow\Dumper\PlantUmlDumper;
use Symfony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symfony\Component\Workflow\Marking;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
class WorkflowDumpCommand extends Command
{
    protected static $defaultName = 'workflow:dump';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::REQUIRED, 'A workflow name'),
                new InputArgument('marking', InputArgument::IS_ARRAY, 'A marking (a list of places)'),
                new InputOption('label', 'l', InputOption::VALUE_REQUIRED, 'Labels a graph'),
                new InputOption('dump-format', null, InputOption::VALUE_REQUIRED, 'The dump format [dot|puml]', 'dot'),
            ])
            ->setDescription('Dump a workflow')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command dumps the graphical representation of a
workflow in different formats

<info>DOT</info>:  %command.full_name% <workflow name> | dot -Tpng > workflow.png
<info>PUML</info>: %command.full_name% <workflow name> --dump-format=puml | java -jar plantuml.jar -p > workflow.png

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getApplication()->getKernel()->getContainer();
        $serviceId = $input->getArgument('name');

        if ($container->has('workflow.'.$serviceId)) {
            $workflow = $container->get('workflow.'.$serviceId);
            $type = 'workflow';
        } elseif ($container->has('state_machine.'.$serviceId)) {
            $workflow = $container->get('state_machine.'.$serviceId);
            $type = 'state_machine';
        } else {
            throw new InvalidArgumentException(sprintf('No service found for "workflow.%1$s" nor "state_machine.%1$s".', $serviceId));
        }

        if ('puml' === $input->getOption('dump-format')) {
            $transitionType = 'workflow' === $type ? PlantUmlDumper::WORKFLOW_TRANSITION : PlantUmlDumper::STATEMACHINE_TRANSITION;
            $dumper = new PlantUmlDumper($transitionType);
        } elseif ('workflow' === $type) {
            $dumper = new GraphvizDumper();
        } else {
            $dumper = new StateMachineGraphvizDumper();
        }

        $marking = new Marking();

        foreach ($input->getArgument('marking') as $place) {
            $marking->mark($place);
        }

        $options = [
            'name' => $serviceId,
            'nofooter' => true,
            'graph' => [
                'label' => $input->getOption('label'),
            ],
        ];
        $output->writeln($dumper->dump($workflow->getDefinition(), $marking, $options));
    }
}
