<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Routing\Matcher\Dumper\ApacheMatcherDumper;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * RouterApacheDumperCommand.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RouterApacheDumperCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('script_name', InputArgument::OPTIONAL, 'The script name of the application\'s front controller.')
            ))
            ->setName('router:dump-apache')
            ->setDescription('Dumps all routes as Apache rewrite rules')
            ->setHelp(<<<EOF
The <info>router:dump-apache</info> dumps all routes as Apache rewrite rules.
These can then be used with the ApacheUrlMatcher to use Apache for route
matching.

  <info>router:dump-apache</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $router = $this->container->get('router');

        $dumpOptions = array();
        if ($input->getArgument('script_name')) {
            $dumpOptions['script_name'] = $input->getArgument('script_name');
        }

        $dumper = new ApacheMatcherDumper($router->getRouteCollection());

        $output->writeln($dumper->dump($dumpOptions), Output::OUTPUT_RAW);
    }
}
