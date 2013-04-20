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

/**
 * A console command for retrieving information about routes
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class RouterDebugCommand extends ContainerAwareCommand
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
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException When route does not exist
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

        $maxName = strlen('name');
        $maxMethod = strlen('method');
        $maxScheme = strlen('scheme');
        $maxHost = strlen('host');

        foreach ($routes as $name => $route) {
            $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
            $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';
            $maxName = max($maxName, strlen($name));
            $maxMethod = max($maxMethod, strlen($method));
            $maxScheme = max($maxScheme, strlen($scheme));
            $maxHost = max($maxHost, strlen($host));
        }

        $format  = '%-'.$maxName.'s %-'.$maxMethod.'s %-'.$maxScheme.'s %-'.$maxHost.'s %s';
        $formatHeader  = '%-'.($maxName + 19).'s %-'.($maxMethod + 19).'s %-'.($maxScheme + 19).'s %-'.($maxHost + 19).'s %s';
        $output->writeln(sprintf($formatHeader, '<comment>Name</comment>', '<comment>Method</comment>',  '<comment>Scheme</comment>', '<comment>Host</comment>', '<comment>Path</comment>'));

        foreach ($routes as $name => $route) {
            $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
            $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
            $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';
            $output->writeln(sprintf($format, $name, $method, $scheme, $host, $route->getPath()), OutputInterface::OUTPUT_RAW);
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

        $method = $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY';
        $scheme = $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY';
        $host = '' !== $route->getHost() ? $route->getHost() : 'ANY';

        $output->write('<comment>Name</comment>         ');
        $output->writeln($name, OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Path</comment>         ');
        $output->writeln($route->getPath(), OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Host</comment>         ');
        $output->writeln($host, OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Scheme</comment>       ');
        $output->writeln($scheme, OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Method</comment>       ');
        $output->writeln($method, OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Class</comment>        ');
        $output->writeln(get_class($route), OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Defaults</comment>     ');
        $output->writeln($this->formatConfigs($route->getDefaults()), OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Requirements</comment> ');
        // we do not want to show the schemes and methods again that are also in the requirements for BC
        $requirements = $route->getRequirements();
        unset($requirements['_scheme'], $requirements['_method']);
        $output->writeln($this->formatConfigs($requirements) ?: 'NO CUSTOM', OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Options</comment>      ');
        $output->writeln($this->formatConfigs($route->getOptions()), OutputInterface::OUTPUT_RAW);

        $output->write('<comment>Path-Regex</comment>   ');
        $output->writeln($route->compile()->getRegex(), OutputInterface::OUTPUT_RAW);

        if (null !== $route->compile()->getHostRegex()) {
            $output->write('<comment>Host-Regex</comment>   ');
            $output->writeln($route->compile()->getHostRegex(), OutputInterface::OUTPUT_RAW);
        }
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

    private function formatConfigs(array $array)
    {
        $string = '';
        ksort($array);
        foreach ($array as $name => $value) {
            $string .= ($string ? "\n".str_repeat(' ', 13) : '').$name.': '.$this->formatValue($value);
        }

        return $string;
    }
}
