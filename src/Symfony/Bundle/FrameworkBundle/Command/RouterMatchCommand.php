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
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;

/**
 * A console command to test route matching.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterMatchCommand extends ContainerAwareCommand
{
    /**
     * {@inheritDoc}
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
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('router:match')
            ->setDefinition(array(
                new InputArgument('path_info', InputArgument::REQUIRED, 'A path info'),
            ))
            ->setDescription('Helps debug routes by simulating a path info match')
            ->setHelp(<<<EOF
The <info>%command.name%</info> simulates a path info match:

  <info>php %command.full_name% /foo</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $router = $this->getContainer()->get('router');
        $matcher = new TraceableUrlMatcher($router->getRouteCollection(), $router->getContext());

        $traces = $matcher->getTraces($input->getArgument('path_info'));

        $matches = false;
        foreach ($traces as $i => $trace) {
            if (TraceableUrlMatcher::ROUTE_ALMOST_MATCHES == $trace['level']) {
                $output->writeln(sprintf('<fg=yellow>Route "%s" almost matches but %s</>', $trace['name'], lcfirst($trace['log'])));
            } elseif (TraceableUrlMatcher::ROUTE_MATCHES == $trace['level']) {
                $output->writeln(sprintf('<fg=green>Route "%s" matches</>', $trace['name']));
                $matches = true;
            } elseif ($input->getOption('verbose')) {
                $output->writeln(sprintf('Route "%s" does not match: %s', $trace['name'], $trace['log']));
            }
        }

        if (!$matches) {
            $output->writeln('<fg=red>None of the routes matches</>');
        }
    }
}
