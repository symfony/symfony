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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * A console command for retrieving information about routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 *
 * @final
 */
class RouterDebugCommand extends Command
{
    protected static $defaultName = 'debug:router';
    private $router;

    public function __construct(RouterInterface $router)
    {
        parent::__construct();

        $this->router = $router;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'A route name'),
                new InputOption('show-controllers', null, InputOption::VALUE_NONE, 'Show assigned controllers in overview'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (txt, xml, json, or md)', 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw route(s)'),
                new InputOption('method', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'To filter by method'),
                new InputOption('scheme', null, InputOption::VALUE_REQUIRED, 'To filter by scheme (http or https)'),
                new InputOption('match-host', null, InputOption::VALUE_REQUIRED, 'To filter by host with a regex'),
            ])
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
     * @throws InvalidArgumentException When route does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $helper = new DescriptorHelper();
        $routes = $this->router->getRouteCollection();

        if ($name) {
            if (!($route = $routes->get($name)) && $matchingRoutes = $this->findRouteNameContaining($name, $routes)) {
                $default = 1 === \count($matchingRoutes) ? $matchingRoutes[0] : null;
                $name = $io->choice('Select one of the matching routes', $matchingRoutes, $default);
                $route = $routes->get($name);
            }

            if (!$route) {
                throw new InvalidArgumentException(sprintf('The route "%s" does not exist.', $name));
            }

            $helper->describe($io, $route, [
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'name' => $name,
                'output' => $io,
            ]);
        } else {
            $routes = $this->filterRoutes($routes, $input);

            $helper->describe($io, $routes, [
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'show_controllers' => $input->getOption('show-controllers'),
                'output' => $io,
            ]);
        }
    }

    private function findRouteNameContaining(string $name, RouteCollection $routes): array
    {
        $foundRoutesNames = [];
        foreach ($routes as $routeName => $route) {
            if (false !== stripos($routeName, $name)) {
                $foundRoutesNames[] = $routeName;
            }
        }

        return $foundRoutesNames;
    }

    private function filterRoutes(RouteCollection $routes, InputInterface $input): RouteCollection
    {
        if (!empty($methods = $input->getOption('method'))) {
            $routes = $this->filter($routes, function (Route $route) use ($methods) {
                return \array_intersect($methods, $route->getMethods()) || empty($route->getMethods());
            });
        }

        if (null !== ($scheme = $input->getOption('scheme'))) {
            $routes = $this->filter($routes, function (Route $route) use ($scheme) {
                return \in_array(\strtolower($scheme), $route->getSchemes(), true) || empty($route->getSchemes());
            });
        }

        if (null !== ($hostRegex = $input->getOption('match-host'))) {
            try {
                $routes = $this->filter($routes, function (Route $route) use ($hostRegex) {
                    return (bool) \preg_match('/'.$hostRegex.'/', $route->getHost()) || empty($route->getHost());
                });
            } catch (\Throwable $e) {
                throw new InvalidArgumentException(\sprintf('"%s" does not seems to be a valid regex.', $hostRegex));
            }
        }

        return $routes;
    }

    private function filter(RouteCollection $routes, \Closure $closure): RouteCollection
    {
        $filteredRoutes = new RouteCollection();
        foreach (\array_filter($routes->all(), $closure) as $name => $route) {
            $filteredRoutes->add($name, $route);
        }

        return $filteredRoutes;
    }
}
