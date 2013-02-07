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
use Symfony\Component\Console\Output\Output;

/**
 * A console command for retrieving information about routes
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RouterDebugCommand extends ContainerAwareCommand
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
            ->setName('router:debug')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A route name'),
            ))
            ->setDescription('Displays current routes for an application')
            ->setHelp(<<<EOF
The <info>%command.name%</info> displays the configured routes:

  <info>php %command.full_name%</info>
EOF
            )
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        if ($name) {
            $this->outputRoute($output, $name);
        } else {
            $this->outputRoutes($output);
        }
    }

    protected function outputRoutes(OutputInterface $output, $routes = null)
    {
        if (null === $routes) {
            $routes = $this->getContainer()->get('router')->getRouteCollection()->all();
        }

        $output->writeln($this->getHelper('formatter')->formatSection('router', 'Current routes'));

        $maxName = 4;
        $maxMethod = 6;
        foreach ($routes as $name => $route) {
            $requirements = $route->getRequirements();
            $method = isset($requirements['_method'])
                ? strtoupper(is_array($requirements['_method'])
                    ? implode(', ', $requirements['_method']) : $requirements['_method']
                )
                : 'ANY';

            if (strlen($name) > $maxName) {
                $maxName = strlen($name);
            }

            if (strlen($method) > $maxMethod) {
                $maxMethod = strlen($method);
            }
        }
        $format  = '%-'.$maxName.'s %-'.$maxMethod.'s %s';

        // displays the generated routes
        $format1  = '%-'.($maxName + 19).'s %-'.($maxMethod + 19).'s %s';
        $output->writeln(sprintf($format1, '<comment>Name</comment>', '<comment>Method</comment>', '<comment>Pattern</comment>'));
        foreach ($routes as $name => $route) {
            $requirements = $route->getRequirements();
            $method = isset($requirements['_method'])
                ? strtoupper(is_array($requirements['_method'])
                    ? implode(', ', $requirements['_method']) : $requirements['_method']
                )
                : 'ANY';
            $output->writeln(sprintf($format, $name, $method, $route->getPattern()));
        }
    }

    /**
     * @throws \InvalidArgumentException When route does not exist
     */
    protected function outputRoute(OutputInterface $output, $name)
    {
        $route = $this->getContainer()->get('router')->getRouteCollection()->get($name);
        if (!$route) {
            throw new \InvalidArgumentException(sprintf('The route "%s" does not exist.', $name));
        }

        $output->writeln($this->getHelper('formatter')->formatSection('router', sprintf('Route "%s"', $name)));

        $output->writeln(sprintf('<comment>Name</comment>         %s', $name));
        $output->writeln(sprintf('<comment>Pattern</comment>      %s', $route->getPattern()));
        $output->writeln(sprintf('<comment>Class</comment>        %s', get_class($route)));

        $defaults = '';
        $d = $route->getDefaults();
        ksort($d);
        foreach ($d as $name => $value) {
            $defaults .= ($defaults ? "\n".str_repeat(' ', 13) : '').$name.': '.$this->formatValue($value);
        }
        $output->writeln(sprintf('<comment>Defaults</comment>     %s', $defaults));

        $requirements = '';
        $r = $route->getRequirements();
        ksort($r);
        foreach ($r as $name => $value) {
            $requirements .= ($requirements ? "\n".str_repeat(' ', 13) : '').$name.': '.$this->formatValue($value);
        }
        $output->writeln(sprintf('<comment>Requirements</comment> %s', $requirements));

        $options = '';
        $o = $route->getOptions();
        ksort($o);
        foreach ($o as $name => $value) {
            $options .= ($options ? "\n".str_repeat(' ', 13) : '').$name.': '.$this->formatValue($value);
        }
        $output->writeln(sprintf('<comment>Options</comment>      %s', $options));
        $output->write('<comment>Regex</comment>        ');
        $output->writeln(preg_replace('/^             /', '', preg_replace('/^/m', '             ', $route->compile()->getRegex())), OutputInterface::OUTPUT_RAW);
    }

    protected function formatValue($value)
    {
        if (is_object($value)) {
            return sprintf('object(%s)', get_class($value));
        }

        if (is_string($value)) {
            return $value;
        }

        return preg_replace("/\n\s*/s", '', var_export($value, true));
    }
}
