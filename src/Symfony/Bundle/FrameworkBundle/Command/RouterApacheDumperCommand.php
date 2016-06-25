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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Matcher\Dumper\ApacheMatcherDumper;
use Symfony\Component\Routing\RouterInterface;

/**
 * RouterApacheDumperCommand.
 *
 * @deprecated since version 2.5, to be removed in 3.0.
 *             The performance gains are minimal and it's very hard to replicate
 *             the behavior of PHP implementation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterApacheDumperCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        if (!$this->getContainer()->has('router')) {
            return false;
        }
        $router = $this->getContainer()->get('router');
        if (!$router instanceof RouterInterface) {
            return false;
        }

        return parent::isEnabled();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('router:dump-apache')
            ->setDefinition(array(
                new InputArgument('script_name', InputArgument::OPTIONAL, 'The script name of the application\'s front controller'),
                new InputOption('base-uri', null, InputOption::VALUE_REQUIRED, 'The base URI'),
            ))
            ->setDescription('[DEPRECATED] Dumps all routes as Apache rewrite rules')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> dumps all routes as Apache rewrite rules.
These can then be used with the ApacheUrlMatcher to use Apache for route
matching.

  <info>php %command.full_name%</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formatter = $this->getHelper('formatter');

        $output->writeln($formatter->formatSection('warning', 'The router:dump-apache command is deprecated since version 2.5 and will be removed in 3.0', 'comment'));

        $router = $this->getContainer()->get('router');

        $dumpOptions = array();
        if ($input->getArgument('script_name')) {
            $dumpOptions['script_name'] = $input->getArgument('script_name');
        }
        if ($input->getOption('base-uri')) {
            $dumpOptions['base_uri'] = $input->getOption('base-uri');
        }

        $dumper = new ApacheMatcherDumper($router->getRouteCollection());

        $output->writeln($dumper->dump($dumpOptions), OutputInterface::OUTPUT_RAW);
    }
}
