<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Components\Routing\Matcher\Dumper\ApacheMatcherDumper;

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
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class RouterApacheDumperCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('router:dump-apache')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $router = $this->container->get('router');

        $dumper = new ApacheMatcherDumper($router->getRouteCollection());

        $output->writeln($dumper->dump(), Output::OUTPUT_RAW);
    }
}
