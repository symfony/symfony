<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineBundle\Command\Proxy;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;
use Doctrine\ORM\Tools\Console\Command\RunDqlCommand;

/**
 * Execute a Doctrine DQL query and output the results.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class RunDqlDoctrineCommand extends RunDqlCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('doctrine:query:dql')
            ->addOption('em', null, InputOption::VALUE_OPTIONAL, 'The entity manager to use for this command')
            ->setHelp(<<<EOT
The <info>doctrine:query:dql</info> command executes the given DQL query and
outputs the results:

<info>php app/console doctrine:query:dql "SELECT u FROM UserBundle:User u"</info>

You can also optional specify some additional options like what type of
hydration to use when executing the query:

<info>php app/console doctrine:query:dql "SELECT u FROM UserBundle:User u" --hydrate=array</info>

Additionally you can specify the first result and maximum amount of results to
show:

<info>php app/console doctrine:query:dql "SELECT u FROM UserBundle:User u" --first-result=0 --max-result=30</info>
EOT
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        DoctrineCommandHelper::setApplicationEntityManager($this->getApplication(), $input->getOption('em'));

        return parent::execute($input, $output);
    }
}
