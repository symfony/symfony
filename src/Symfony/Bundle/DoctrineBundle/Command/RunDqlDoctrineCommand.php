<?php

namespace Symfony\Bundle\DoctrineBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Execute a Doctrine DQL query and output the results.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 * @author     Jonathan H. Wage <jonwage@gmail.com>
 */
class RunDqlDoctrineCommand extends RunDqlCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:query:dql')
            ->addOption('em', null, InputOption::PARAMETER_OPTIONAL, 'The entity manager to use for this command.')
            ->setHelp(<<<EOT
The <info>doctrine:query:dql</info> command executes the given DQL query and outputs the results:

  <info>./symfony doctrine:query:dql "SELECT u FROM UserBundle:User u"</info>

You can also optional specify some additional options like what type of hydration to use when executing the query:

  <info>./symfony doctrine:query:dql "SELECT u FROM UserBundle:User u" --hydrate=array</info>

Additionaly you can specify the first result and maximum amount of results to show:

  <info>./symfony doctrine:query:dql "SELECT u FROM UserBundle:User u" --first-result=0 --max-result=30</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommand::setApplicationEntityManager($this->application, $input->getOption('em'));

        return parent::execute($input, $output);
    }
}