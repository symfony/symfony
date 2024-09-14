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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
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
#[AsCommand(name: 'debug:router', description: 'Display current routes for an application')]
class RouterDebugCommand extends Command
{
    use BuildDebugContainerTrait;

    public function __construct(
        private RouterInterface $router,
        private ?FileLinkFormatter $fileLinkFormatter = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'A route name'),
                new InputOption('show-controllers', null, InputOption::VALUE_NONE, 'Show assigned controllers in overview'),
                new InputOption('show-aliases', null, InputOption::VALUE_NONE, 'Show aliases in overview'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, \sprintf('The output format ("%s")', implode('", "', $this->getAvailableFormatOptions())), 'txt'),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'To output raw route(s)'),
                new InputOption('sort', null, InputOption::VALUE_REQUIRED, 'Sort by column name'),
            ])
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> displays the configured routes:

  <info>php %command.full_name%</info>

The <info>--format</info> option specifies the format of the command output:

  <info>php %command.full_name% --format=json</info>
EOF
            )
        ;
    }

    /**
     * @throws InvalidArgumentException When route does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $helper = new DescriptorHelper($this->fileLinkFormatter);
        $routes = $this->router->getRouteCollection();
        $container = null;
        if ($this->fileLinkFormatter) {
            $container = fn () => $this->getContainerBuilder($this->getApplication()->getKernel());
        }

        if ($name) {
            $route = $routes->get($name);
            $matchingRoutes = $this->findRouteNameContaining($name, $routes);

            if (!$input->isInteractive() && !$route && \count($matchingRoutes) > 1) {
                $helper->describe($io, $this->findRouteContaining($name, $routes), [
                    'format' => $input->getOption('format'),
                    'raw_text' => $input->getOption('raw'),
                    'show_controllers' => $input->getOption('show-controllers'),
                    'show_aliases' => $input->getOption('show-aliases'),
                    'output' => $io,
                ]);

                return 0;
            }

            if (!$route && $matchingRoutes) {
                $default = 1 === \count($matchingRoutes) ? $matchingRoutes[0] : null;
                $name = $io->choice('Select one of the matching routes', $matchingRoutes, $default);
                $route = $routes->get($name);
            }

            if (!$route) {
                throw new InvalidArgumentException(\sprintf('The route "%s" does not exist.', $name));
            }

            $helper->describe($io, $route, [
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'name' => $name,
                'output' => $io,
                'container' => $container,
            ]);
        } else {
            $sortBy = $input->getOption('sort');
            if ($sortBy) {
                $routes = $this->sortBy($routes, \strtolower($sortBy));
            }
            $helper->describe($io, $routes, [
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'sort' => $input->getOption('sort'),
                'show_controllers' => $input->getOption('show-controllers'),
                'show_aliases' => $input->getOption('show-aliases'),
                'output' => $io,
                'container' => $container,
            ]);
        }

        return 0;
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

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues(array_keys($this->router->getRouteCollection()->all()));

            return;
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues($this->getAvailableFormatOptions());
        }
    }

    private function findRouteContaining(string $name, RouteCollection $routes): RouteCollection
    {
        $foundRoutes = new RouteCollection();
        foreach ($routes as $routeName => $route) {
            if (false !== stripos($routeName, $name)) {
                $foundRoutes->add($routeName, $route);
            }
        }

        return $foundRoutes;
    }

    /** @return string[] */
    private function getAvailableFormatOptions(): array
    {
        return (new DescriptorHelper())->getFormats();
    }

    private function sortBy(RouteCollection $routeCollection, string $column): RouteCollection
    {
        $routesArray = $routeCollection->all();
        $sortedRoutes = new RouteCollection();

        if ('name' === $column) {
            \ksort($routesArray);
            foreach ($routesArray as $name => $route) {
                $sortedRoutes->add($name, $route);
                $sortedRoutes->addAlias($route->getDefault('_controller'), $name);
            }

            return $sortedRoutes;
        }

        $helperArray = [];

        if ('method' === $column) {
            foreach ($routesArray as $name => $route) {
                $methods = empty($route->getMethods()) ? 'ANY' : \implode('|', $route->getMethods());
                $helperArray[$methods][$name] = $route;
            }
        }

        if ('scheme' === $column) {
            foreach ($routesArray as $name => $route) {
                $scheme = empty($route->getSchemes()) ? 'ANY' : \implode('|', $route->getSchemes());
                $helperArray[$scheme][$name] = $route;
            }
        }

        if ('host' === $column) {
            foreach ($routesArray as $name => $route) {
                $helperArray[$route->getHost()][$name] = $route;

            }
        }

        if ('path' === $column) {
            foreach ($routesArray as $name => $route) {
                $helperArray[$route->getPath()][$name] = $route;
            }
        }

        \ksort($helperArray);
        foreach ($helperArray as $array) {
            foreach ($array as $name => $route) {
                $sortedRoutes->add($name, $route);
                $sortedRoutes->addAlias($route->getDefault('_controller'), $name);
            }
        }

        return $sortedRoutes;
    }
}
