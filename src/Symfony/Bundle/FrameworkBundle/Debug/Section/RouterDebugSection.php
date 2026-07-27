<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Symfony\Bundle\FrameworkBundle\Command\BuildDebugContainerTrait;
use Symfony\Bundle\FrameworkBundle\Console\Helper\DescriptorHelper;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\ErrorHandler\ErrorRenderer\FileLinkFormatter;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * The "Routes" tab of the interactive "debug" command.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class RouterDebugSection extends AbstractDebugSection
{
    use BuildDebugContainerTrait;

    public function __construct(
        private readonly RouterInterface $router,
        private readonly KernelInterface $kernel,
        private readonly ?FileLinkFormatter $fileLinkFormatter = null,
    ) {
    }

    public function getLabel(): string
    {
        return 'Routes';
    }

    public function getShortLabel(): string
    {
        return 'Routes';
    }

    public function describe(DebugItem $item, int $width): string
    {
        $route = $this->router->getRouteCollection()->get($item->value);
        if (!$route) {
            return \sprintf('Route "%s" does not exist.', $item->value);
        }

        return $this->describeToBuffer(function (SymfonyStyle $io) use ($route, $item): void {
            (new DescriptorHelper($this->fileLinkFormatter))->describe($io, $route, [
                'format' => 'txt',
                'name' => $item->value,
                'output' => $io,
                'container' => $this->fileLinkFormatter ? fn () => $this->getContainerBuilder($this->kernel) : null,
            ]);
        });
    }

    /**
     * Builds the full, unfiltered item list once. Recomputing it on every keystroke
     * would be costly on large applications.
     *
     * @return list<DebugItem>
     */
    protected function buildItems(): array
    {
        $routes = $this->router->getRouteCollection();
        $items = [];

        foreach ($routes->all() as $name => $route) {
            $items[] = new DebugItem('route', $name, $name, searchText: implode("\n", array_filter([
                $route->getPath(),
                $route->getHost(),
                implode(' ', $route->getMethods()),
                $route->getDefault('_controller'),
            ], static fn (mixed $value): bool => \is_scalar($value) && '' !== (string) $value)) ?: null);
        }

        foreach ($routes->getAliases() as $name => $alias) {
            $route = $routes->get($name);
            if (!$route) {
                continue;
            }

            $items[] = new DebugItem('route', $name, $name, searchText: implode("\n", array_filter([
                $alias->getId(),
                $route->getPath(),
                $route->getHost(),
                implode(' ', $route->getMethods()),
                $route->getDefault('_controller'),
            ], static fn (mixed $value): bool => \is_scalar($value) && '' !== (string) $value)) ?: null);
        }

        return $items;
    }
}
