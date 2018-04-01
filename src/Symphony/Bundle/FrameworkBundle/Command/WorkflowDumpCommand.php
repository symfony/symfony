<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Command;

use Symphony\Component\Console\Command\Command;
use Symphony\Component\Console\Input\InputArgument;
use Symphony\Component\Console\Input\InputInterface;
use Symphony\Component\Console\Input\InputOption;
use Symphony\Component\Console\Output\OutputInterface;
use Symphony\Component\Workflow\Dumper\GraphvizDumper;
use Symphony\Component\Workflow\Dumper\PlantUmlDumper;
use Symphony\Component\Workflow\Dumper\StateMachineGraphvizDumper;
use Symphony\Component\Workflow\Marking;

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
            ->setDefinition(array(
                new InputArgument('name', InputArgument::REQUIRED, 'A workflow name'),
                new InputArgument('marking', InputArgument::IS_ARRAY, 'A marking (a list of places)'),
                new InputOption('label', 'l', InputOption::VALUE_REQUIRED, 'Labels a graph'),
                new InputOption('dump-format', null, InputOption::VALUE_REQUIRED, 'The dump format [dot|puml]', 'dot'),
            ))
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
            throw new \InvalidArgumentException(sprintf('No service found for "workflow.%1$s" nor "state_machine.%1$s".', $serviceId));
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

        $options = array(
            'name' => $serviceId,
            'nofooter' => true,
            'graph' => array(
                'label' => $input->getOption('label'),
            ),
        );
        $output->writeln($dumper->dump($workflow->getDefinition(), $marking, $options));
    }
}
