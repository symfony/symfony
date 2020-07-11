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
use Symfony\Component\HttpKernel\Debug\FileLinkFormatter;
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
    use BuildDebugContainerTrait;

    protected static $defaultName = 'debug:router';
    private $router;
    private $fileLinkFormatter;

    public function __construct(RouterInterface $router, FileLinkFormatter $fileLinkFormatter = null)
    {
        parent::__construct();

        $this->router = $router;
        $this->fileLinkFormatter = $fileLinkFormatter;
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
                new InputOption('sort', null, InputOption::VALUE_REQUIRED, 'The sorting field (priority, name, or path)', 'priority'),
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
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $helper = new DescriptorHelper($this->fileLinkFormatter);
        $container = $this->fileLinkFormatter ? \Closure::fromCallable([$this, 'getContainerBuilder']) : null;
        $sortOption = $input->getOption('sort');
        $routes = $this->sortRoutes($this->router->getRouteCollection(), $sortOption);
        if ('priority' !== $sortOption) {
            $io->caution('The routes list is not sorted in the parsing order.');
        }

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
                'container' => $container,
            ]);
        } else {
            $helper->describe($io, $routes, [
                'format' => $input->getOption('format'),
                'raw_text' => $input->getOption('raw'),
                'show_controllers' => $input->getOption('show-controllers'),
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

    private function sortRoutes(RouteCollection $routes, string $propertyName): RouteCollection
    {
        $sortedRoutes = $routes->all();
        if ('name' === $propertyName) {
            ksort($sortedRoutes);
        } elseif ('path' === $propertyName) {
            uasort($sortedRoutes, function ($a, $b) { return $a->getPath() <=> $b->getPath(); });
        } elseif ('priority' !== $propertyName) {
            throw new InvalidArgumentException(sprintf('The option "%s" is not valid.', $propertyName));
        }
        $routeCollection = new RouteCollection();
        foreach ($sortedRoutes as $routeName => $route) {
            $routeCollection->add($routeName, $route);
        }

        return $routeCollection;
    }
}
