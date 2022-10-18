<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Routing;

use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileExistenceResource;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\DependencyInjection\ContainerInterface as SymfonyContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router as BaseRouter;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * This Router creates the Loader only when the cache is empty.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Router extends BaseRouter implements WarmableInterface, ServiceSubscriberInterface
{
    private ContainerInterface $container;
    private array $collectedParameters = [];
    private \Closure $paramFetcher;

    /**
     * @param mixed $resource The main resource to load
     */
    public function __construct(ContainerInterface $container, mixed $resource, array $options = [], RequestContext $context = null, ContainerInterface $parameters = null, LoggerInterface $logger = null, string $defaultLocale = null)
    {
        $this->container = $container;
        $this->resource = $resource;
        $this->context = $context ?? new RequestContext();
        $this->logger = $logger;
        $this->setOptions($options);

        if ($parameters) {
            $this->paramFetcher = $parameters->get(...);
        } elseif ($container instanceof SymfonyContainerInterface) {
            $this->paramFetcher = $container->getParameter(...);
        } else {
            throw new \LogicException(sprintf('You should either pass a "%s" instance or provide the $parameters argument of the "%s" method.', SymfonyContainerInterface::class, __METHOD__));
        }

        $this->defaultLocale = $defaultLocale;
    }

    public function getRouteCollection(): RouteCollection
    {
        if (null === $this->collection) {
            $this->collection = $this->container->get('routing.loader')->load($this->resource, $this->options['resource_type']);
            $this->resolveParameters($this->collection);
            $this->collection->addResource(new ContainerParametersResource($this->collectedParameters));

            try {
                $containerFile = ($this->paramFetcher)('kernel.cache_dir').'/'.($this->paramFetcher)('kernel.container_class').'.php';
                if (file_exists($containerFile)) {
                    $this->collection->addResource(new FileResource($containerFile));
                } else {
                    $this->collection->addResource(new FileExistenceResource($containerFile));
                }
            } catch (ParameterNotFoundException) {
            }
        }

        return $this->collection;
    }

    /**
     * @return string[] A list of classes to preload on PHP 7.4+
     */
    public function warmUp(string $cacheDir): array
    {
        $currentDir = $this->getOption('cache_dir');

        // force cache generation
        $this->setOption('cache_dir', $cacheDir);
        $this->getMatcher();
        $this->getGenerator();

        $this->setOption('cache_dir', $currentDir);

        return [
            $this->getOption('generator_class'),
            $this->getOption('matcher_class'),
        ];
    }

    /**
     * Replaces placeholders with service container parameter values in:
     * - the route defaults,
     * - the route requirements,
     * - the route path,
     * - the route host,
     * - the route schemes,
     * - the route methods.
     */
    private function resolveParameters(RouteCollection $collection)
    {
        foreach ($collection as $route) {
            foreach ($route->getDefaults() as $name => $value) {
                $route->setDefault($name, $this->resolve($value));
            }

            foreach ($route->getRequirements() as $name => $value) {
                $route->setRequirement($name, $this->resolve($value));
            }

            $route->setPath($this->resolve($route->getPath()));
            $route->setHost($this->resolve($route->getHost()));

            $schemes = [];
            foreach ($route->getSchemes() as $scheme) {
                $schemes[] = explode('|', $this->resolve($scheme));
            }
            $route->setSchemes(array_merge([], ...$schemes));

            $methods = [];
            foreach ($route->getMethods() as $method) {
                $methods[] = explode('|', $this->resolve($method));
            }
            $route->setMethods(array_merge([], ...$methods));
            $route->setCondition($this->resolve($route->getCondition()));
        }
    }

    /**
     * Recursively replaces %placeholders% with the service container parameters.
     *
     * @throws ParameterNotFoundException When a placeholder does not exist as a container parameter
     * @throws RuntimeException           When a container value is not a string or a numeric value
     */
    private function resolve(mixed $value): mixed
    {
        if (\is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->resolve($val);
            }

            return $value;
        }

        if (!\is_string($value)) {
            return $value;
        }

        $escapedValue = preg_replace_callback('/%%|%([^%\s]++)%/', function ($match) use ($value) {
            // skip %%
            if (!isset($match[1])) {
                return '%%';
            }

            if (preg_match('/^env\((?:\w++:)*+\w++\)$/', $match[1])) {
                throw new RuntimeException(sprintf('Using "%%%s%%" is not allowed in routing configuration.', $match[1]));
            }

            $resolved = ($this->paramFetcher)($match[1]);

            if (\is_scalar($resolved)) {
                $this->collectedParameters[$match[1]] = $resolved;

                if (\is_string($resolved)) {
                    $resolved = $this->resolve($resolved);
                }

                if (\is_scalar($resolved)) {
                    return false === $resolved ? '0' : (string) $resolved;
                }
            }

            throw new RuntimeException(sprintf('The container parameter "%s", used in the route configuration value "%s", must be a string or numeric, but it is of type "%s".', $match[1], $value, get_debug_type($resolved)));
        }, $value);

        return str_replace('%%', '%', $escapedValue);
    }

    public static function getSubscribedServices(): array
    {
        return [
            'routing.loader' => LoaderInterface::class,
        ];
    }
}
