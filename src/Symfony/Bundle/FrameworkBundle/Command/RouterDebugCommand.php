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

use Symfony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;

/**
 * A console command for retrieving information about routes.
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
            ->setName('debug:router')
            ->setAliases(array(
                'router:debug',
            ))
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A route name'),
                new InputOption('show-controllers', null,  InputOption::VALUE_NONE, 'Show assigned controllers in overview'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'To output route(s) in other formats', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw route(s)'),
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
        $output = new SymfonyStyle($input, $output);

        if (false !== strpos($input->getFirstArgument(), ':d')) {
            $output->caution('The use of "router:debug" command is deprecated since version 2.7 and will be removed in 3.0. Use the "debug:router" instead.');
        }

        $name = $input->getArgument('name');
        $helper = new DescriptorHelper();

        if ($name) {
            $route = $this->getContainer()->get('router')->getRouteCollection()->get($name);
            if (!$route) {
                throw new \InvalidArgumentException(sprintf('The route "%s" does not exist.', $name));
            }

            $this->convertController($route);

            if ('txt' === $input->getOption('format')) {
                $this->displayRouteInformation($name, $route, $output);
            } else {
                $helper->describe($output, $route, array(
                    'format' => $input->getOption('format'),
                    'raw_text' => $input->getOption('raw'),
                    'name' => $name,
                ));
            }
        } else {
            $routes = $this->getContainer()->get('router')->getRouteCollection();

            foreach ($routes as $route) {
                $this->convertController($route);
            }

            if ('txt' === $input->getOption('format')) {
                $this->displayRouteCollectionInformation($routes, $input->getOption('show-controllers'), $output);
            } else {
                $helper->describe($output, $routes, array(
                    'format' => $input->getOption('format'),
                    'raw_text' => $input->getOption('raw'),
                    'show_controllers' => $input->getOption('show-controllers'),
                ));
            }
        }
    }

    private function displayRouteInformation($name, Route $route, OutputInterface $output)
    {
        $requirements = $route->getRequirements();

        $output->table(
            array('Property', 'Value'),
            array(
                array('Route Name', $name),
                array('Path', $route->getPath()),
                array('Path Regex', $route->compile()->getRegex()),
                array('Host', ('' !== $route->getHost() ? $route->getHost() : 'ANY')),
                array('Host Regex', ('' !== $route->getHost() ? $route->compile()->getHostRegex() : '')),
                array('Scheme', ($route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY')),
                array('Method', ($route->getMethods() ? implode('|', $route->getMethods()) : 'ANY')),
                array('Requirements', ($requirements ? $this->formatRouterConfig($requirements) : 'NO CUSTOM')),
                array('Class', get_class($route)),
                array('Defaults', $this->formatRouterConfig($route->getDefaults())),
                array('Options', $this->formatRouterConfig($route->getOptions())),
            )
        );
    }

    private function displayRouteCollectionInformation(RouteCollection $routes, $showControllers, OutputInterface $output)
    {
        $tableHeaders = array('Name', 'Method', 'Scheme', 'Host', 'Path');
        if ($showControllers) {
            $tableHeaders[] = 'Controller';
        }

        $tableRows = array();
        foreach ($routes->all() as $name => $route) {
            $row = array(
                $name,
                $route->getMethods() ? implode('|', $route->getMethods()) : 'ANY',
                $route->getSchemes() ? implode('|', $route->getSchemes()) : 'ANY',
                '' !== $route->getHost() ? $route->getHost() : 'ANY',
                $route->getPath(),
            );

            if ($showControllers) {
                $controller = $route->getDefault('_controller');
                if ($controller instanceof \Closure) {
                    $controller = 'Closure';
                } elseif (is_object($controller)) {
                    $controller = get_class($controller);
                }
                $row[] = $controller;
            }

            $tableRows[] = $row;
        }

        $output->table($tableHeaders, $tableRows);
    }

    private function formatRouterConfig(array $config)
    {
        if (!count($config)) {
            return 'NONE';
        }

        $string = '';
        ksort($config);
        foreach ($config as $name => $value) {
            $string .= sprintf("\n%s: %s", $name, $this->formatValue($value));
        }

        return trim($string);
    }

    private function formatValue($value)
    {
        if (is_object($value)) {
            return sprintf('object(%s)', get_class($value));
        }

        if (is_string($value)) {
            return $value;
        }

        return preg_replace("/\n\s*/s", '', var_export($value, true));
    }

    private function convertController(Route $route)
    {
        $nameParser = $this->getContainer()->get('controller_name_converter');
        if ($route->hasDefault('_controller')) {
            try {
                $route->setDefault('_controller', $nameParser->build($route->getDefault('_controller')));
            } catch (\InvalidArgumentException $e) {
            }
        }
    }
}
