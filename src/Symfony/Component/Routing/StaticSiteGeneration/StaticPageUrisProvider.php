<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\StaticSiteGeneration;

use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\InvalidArgumentException;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Thomas Bibaut <bibaut.t@gmail.com>
 */
final readonly class StaticPageUrisProvider implements StaticPageUrisProviderInterface
{
    public function __construct(
        private RouterInterface $router,
        private ContainerInterface $paramsProviders,
    ) {
    }

    /**
     * WARNING: This method should never be used at runtime as it is SLOW.
     *
     * @return iterable<string>
     *
     * @throws LogicException           if a route noted static is not configured properly
     * @throws InvalidArgumentException if some route parameters cannot be met
     */
    public function provide(): iterable
    {
        foreach ($this->router->getRouteCollection() as $routeName => $route) {
            /** @var bool|array{params?: string|iterable<array<mixed>>} */
            $config = $route->getDefaults()['_static_generation'] ?? null;
            if (!$config) {
                continue;
            }

            if ([] !== $route->getMethods() && !\in_array(Request::METHOD_GET, $route->getMethods(), true)) {
                throw new LogicException(\sprintf('Expected route "%s" to accept GET method, it accepts "%s" only.', $routeName, implode(', ', $route->getMethods())));
            }

            if (false === ($route->getDefaults()['_stateless'] ?? false)) {
                throw new LogicException(\sprintf('Expected route "%s" to be stateless.', $routeName));
            }

            $compiledRoute = $route->compile();

            // $config "true" means no params to match the path variables
            if ([] === $compiledRoute->getPathVariables() || true === $config) {
                yield $this->router->generate($routeName);

                continue;
            }

            if (!isset($config['params'])) {
                throw new LogicException(\sprintf('Missing "params" configuration for route "%s".', $routeName));
            }

            $paramsList = $this->getParamsList($config['params']);
            foreach ($compiledRoute->getPathVariables() as $pathVariable) {
                foreach ($paramsList as $params) {
                    yield $this->router->generate($routeName, $params);
                }
            }
        }
    }

    /**
     * @param string|list<array<string, mixed>> $params
     *
     * @return iterable<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function getParamsList(array|string $params): iterable
    {
        if (\is_string($params)) {
            $serviceId = $params;
            if (!$this->paramsProviders->has($serviceId)) {
                throw new InvalidArgumentException(\sprintf('You have requested a non-existent params provider service "%s". Did you implement "%s"?', $serviceId, ParamsProviderInterface::class));
            }

            $paramsProvider = $this->paramsProviders->get($serviceId);
            if (!$paramsProvider instanceof ParamsProviderInterface) {
                throw new InvalidArgumentException(\sprintf('The "%s" params provider service does not implement "%s".', $serviceId, ParamsProviderInterface::class));
            }

            return $paramsProvider->provideParams();
        }

        return $params;
    }
}
