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

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Matcher\TraceableUrlMatcher;
use Symfony\Component\Routing\RouterInterface;

/**
 * A console command to test route matching.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class RouterOpenCommand extends ContainerAwareCommand
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
            ->setName('router:open')
            ->setDefinition(array(
                new InputArgument('path_info', InputArgument::REQUIRED, 'A path info'),
            ))
            ->setDescription('Open the controller that matches the path_info')
            ->setHelp(<<<EOF
The <info>%command.name%</info> open in your favorite text editor the matching path_info:

  <info>php %command.full_name% /foo</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
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
                $route = $this->getContainer()->get('router')->getRouteCollection()->get($trace['name']);
                $controller = $route->getDefault('_controller');
                $controllerResolver = $this->getContainer()->get('debug.controller_resolver');
                $request = new Request();
                $request->attributes->set('_controller', $controller);

                $this->open($controllerResolver->getController($request), $output);

                $matches = true;
            } elseif ($input->getOption('verbose')) {
                $output->writeln(sprintf('Route "%s" does not match: %s', $trace['name'], $trace['log']));
            }
        }

        if (!$matches) {
            $output->writeln('<fg=red>None of the routes matches</>');

            return 1;
        }
    }

    private function open(array $callable, OutputInterface $output)
    {
        if (is_object($callable[0])) {
            $class = get_class($callable[0]);
            $method = $callable[1];

            // Create an instance of the ReflectionMethod class
            $method = new \ReflectionMethod($callable[0], $callable[1]);

            $this->doOpen($method->getFileName(), $method->getStartLine(), $output);
        } else {
            $output->writeln('<fg=red>Unable to find the file containing the controller</>');
        }
    }

    private function doOpen($file, $line, OutputInterface $output)
    {
        $fileLinkFormat = $this->getContainer()->getParameter('templating.helper.code.file_link_format') ?: ini_get('xdebug.file_link_format');

        if (!$fileLinkFormat) {
            $output->writeln(sprintf('File: %s, line: "%s"', $file, $line));

            return;
        }

        $link = str_replace(array('%f', '%l'), array($file, $line), $fileLinkFormat);

        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            // What can I do ?
        } elseif (PHP_OS == 'Darwin') {
            $open = 'open';
        } elseif (PHP_OS == 'Linux') {
            $open = 'xdg-open';
        } else {
            throw new \RuntimeException('This command does not support your os');
        }

        $command = sprintf('%s %s', $open,  $link);

        if ($output->isVerbose()) {
            $output->writeln($command);
        }

        exec($command);
    }
}
