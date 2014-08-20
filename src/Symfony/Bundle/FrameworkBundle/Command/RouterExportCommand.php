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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Exporter\RouteExporter;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * A console command to convert route definitions to another format.
 *
 * @author David Tengeri <dtengeri@gmail.com>
 */
class RouterExportCommand extends ContainerAwareCommand
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
            ->setName('router:export')
            ->setDefinition(array(
                new InputOption('format', 'f', InputOption::VALUE_OPTIONAL, 'Target format (yaml, xml, php) of route definitions.', 'yaml'),
                new InputOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output folder.'),
                new InputOption('system', null, InputOption::VALUE_NONE, 'Include system paths.'),
            ))
            ->setDescription('Exports the route definitions in the given format (yaml, xml, php). ')
            ->setHelp(<<<EOF
The <info>%command.name%</info> exports the route definitions in the given format:

  <info>php %command.full_name%</info>

Available formats are:
 - yaml
 - xml
 - php

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException When unknown format is given
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $routes = $this->getContainer()->get('router')->getRouteCollection();
        foreach ($routes->all() as $name => $route) {
            if (!$input->getOption('system') && substr($name, 0, 1) === '_') {
                // Skip paths defined by the framework.
                $routes->remove($name);
                continue;
            }
            $this->convertController($route);
        }

        $destination = $input->getOption('output');
        $format = $input->getOption('format');

        RouteExporter::getExporter($destination, $format)->export($routes);
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