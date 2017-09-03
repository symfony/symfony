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
use Symfony\Bundle\FrameworkBundle\Controller\ControllerNameParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Route;

/**
 * A console command for retrieving information about routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @final since version 3.4
 */
class RouterDebugCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'debug:router';
    private $router;

    /**
     * @param RouterInterface $router
     */
    public function __construct($router = null)
    {
        if (!$router instanceof RouterInterface) {
            @trigger_error(sprintf('%s() expects an instance of "%s" as first argument since version 3.4. Not passing it is deprecated and will throw a TypeError in 4.0.', __METHOD__, RouterInterface::class), E_USER_DEPRECATED);

            parent::__construct($router);

            return;
        }

        parent::__construct();

        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     *
     * BC to be removed in 4.0
     */
    public function isEnabled()
    {
        if (null !== $this->router) {
            return parent::isEnabled();
        }
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
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'A route name'),
                new InputOption('show-controllers', null, InputOption::VALUE_NONE, 'Show assigned controllers in overview'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw route(s)'),
            ))
            ->setDescription('Displays current routes for an application')
            ->setHelp(<<<'EOF'
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
        // BC to be removed in 4.0
        if (null === $this->router) {
            $this->router = $this->getContainer()->get('router');
        }

        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $helper = new DescriptorHelper();
        $routes = $this->router->getRouteCollection();

        if ($name) {
            if (!$route = $routes->get($name)) {
                throw new \InvalidArgumentException(sprintf('The route "%s" does not exist.', $name));
            }

            $callable = $this->extractCallable($route);

            $helper->describe($io, $route, array(
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'name' => $name,
                'output' => $io,
                'callable' => $callable,
            ));
        } else {
            foreach ($routes as $route) {
                $this->convertController($route);
            }

            $helper->describe($io, $routes, array(
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'show_controllers' => $input->getOption('show-controllers'),
                'output' => $io,
            ));
        }
    }

    private function convertController(Route $route)
    {
        if ($route->hasDefault('_controller')) {
            $nameParser = new ControllerNameParser($this->getApplication()->getKernel());
            try {
                $route->setDefault('_controller', $nameParser->build($route->getDefault('_controller')));
            } catch (\InvalidArgumentException $e) {
            }
        }
    }

    private function extractCallable(Route $route)
    {
        if (!$route->hasDefault('_controller')) {
            return;
        }

        $controller = $route->getDefault('_controller');

        if (1 === substr_count($controller, ':')) {
            list($service, $method) = explode(':', $controller);
            try {
                return sprintf('%s::%s', get_class($this->getApplication()->getKernel()->getContainer()->get($service)), $method);
            } catch (ServiceNotFoundException $e) {
            }
        }

        $nameParser = new ControllerNameParser($this->getApplication()->getKernel());
        try {
            $shortNotation = $nameParser->build($controller);
            $route->setDefault('_controller', $shortNotation);

            return $controller;
        } catch (\InvalidArgumentException $e) {
        }
    }
}
