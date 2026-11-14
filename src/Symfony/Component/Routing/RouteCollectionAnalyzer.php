<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing;

use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;

/**
 * Finds the routes that a route declared before them prevents from matching.
 *
 * Each route is checked with sample URLs built from its requirements: a route is
 * reported when an earlier route matches all its samples. As samples cannot cover
 * every URL a requirement accepts, the analysis is a heuristic.
 */
final class RouteCollectionAnalyzer
{
    public const SHADOWED = 'shadowed';
    public const DUPLICATE = 'duplicate';
    public const AMBIGUOUS = 'ambiguous';

    private const SAMPLE_VALUES = ['sample', '1', '42', '123456789', '0', 'a', 'Z', 'en', 'a-b', 'a_b', 'a.b', 'a/b', '2026-01-01'];

    /**
     * @return list<array{type: self::SHADOWED|self::DUPLICATE|self::AMBIGUOUS, severity: 'error'|'warning', route: string, path: string, previous_route: string, previous_path: string, message: string}>
     */
    public function analyze(RouteCollection $routes): array
    {
        $issues = [];
        $previousRoutes = [];

        foreach ($routes->all() as $name => $route) {
            $samples = $this->generateSamples($route);
            $issue = null;

            foreach ($previousRoutes as $previousName => $previousRoute) {
                if ($this->isDuplicate($previousRoute, $route)) {
                    $issue = $this->createIssue(self::DUPLICATE, $name, $route, $previousName, $previousRoute, 'Route "%s" (%s) has the same path, host, methods, schemes and condition as route "%s" (%s) declared before it and can never match.');
                    break;
                }

                if (!$samples || !$this->overlaps($previousRoute, $route, $samples)) {
                    continue;
                }

                if ($this->shadows($previousName, $previousRoute, $route, $samples)) {
                    $issue = $this->createIssue(self::SHADOWED, $name, $route, $previousName, $previousRoute, 'Route "%s" (%s) can never match because route "%s" (%s) declared before it matches all its URLs.');
                    break;
                }

                if (!$issue && $this->hasPlaceholderWithoutRequirement($previousRoute)) {
                    $issue = $this->createIssue(self::AMBIGUOUS, $name, $route, $previousName, $previousRoute, 'Route "%s" (%s) overlaps route "%s" (%s) declared before it, which has placeholders without requirements and matches some of its requests.');
                }
            }

            if ($issue) {
                $issues[] = $issue;
            }

            $previousRoutes[$name] = $route;
        }

        return $issues;
    }

    private function createIssue(string $type, string $name, Route $route, string $previousName, Route $previousRoute, string $message): array
    {
        return [
            'type' => $type,
            'severity' => self::AMBIGUOUS === $type ? 'warning' : 'error',
            'route' => $name,
            'path' => $route->getPath(),
            'previous_route' => $previousName,
            'previous_path' => $previousRoute->getPath(),
            'message' => \sprintf($message, $name, $route->getPath(), $previousName, $previousRoute->getPath()),
        ];
    }

    private function isDuplicate(Route $first, Route $second): bool
    {
        $firstCompiled = $first->compile();
        $secondCompiled = $second->compile();

        return $firstCompiled->getRegex() === $secondCompiled->getRegex()
            && $firstCompiled->getHostRegex() === $secondCompiled->getHostRegex()
            && $this->sort($first->getMethods()) === $this->sort($second->getMethods())
            && $this->sort($first->getSchemes()) === $this->sort($second->getSchemes())
            && $first->getCondition() === $second->getCondition();
    }

    /**
     * Checks whether some requests for all the samples can be matched by both routes.
     *
     * @param list<array{string, string|null}> $samples
     */
    private function overlaps(Route $first, Route $second, array $samples): bool
    {
        $compiled = $first->compile();

        foreach ($samples as [$path, $host]) {
            if (!preg_match($compiled->getRegex(), $path)) {
                return false;
            }

            if (null !== $host && $compiled->getHostRegex() && !preg_match($compiled->getHostRegex(), $host)) {
                return false;
            }
        }

        $firstMethods = $this->expandMethods($first->getMethods());
        $secondMethods = $this->expandMethods($second->getMethods());

        return (!$firstMethods || !$secondMethods || array_intersect($firstMethods, $secondMethods))
            && (!$first->getSchemes() || !$second->getSchemes() || array_intersect($first->getSchemes(), $second->getSchemes()));
    }

    /**
     * Checks whether all the requests for the samples are matched by the first route.
     *
     * @param list<array{string, string|null}> $samples
     */
    private function shadows(string $firstName, Route $first, Route $second, array $samples): bool
    {
        if ('' !== $first->getCondition()) {
            return false;
        }

        if ($first->getMethods() && (!$second->getMethods() || array_diff($second->getMethods(), $this->expandMethods($first->getMethods())))) {
            return false;
        }

        if ($first->getSchemes() && (!$second->getSchemes() || array_diff($second->getSchemes(), $first->getSchemes()))) {
            return false;
        }

        if ('' !== $first->getHost() && '' === $second->getHost()) {
            return false;
        }

        $routes = new RouteCollection();
        $routes->add($firstName, $first);

        foreach ($samples as [$path, $host]) {
            $context = new RequestContext('', $second->getMethods()[0] ?? 'GET', $host ?? 'localhost', $second->getSchemes()[0] ?? 'http');

            try {
                (new UrlMatcher($routes, $context))->match($path);
            } catch (ExceptionInterface) {
                return false;
            }
        }

        return true;
    }

    private function hasPlaceholderWithoutRequirement(Route $route): bool
    {
        foreach ($route->compile()->getPathVariables() as $variable) {
            if (!$route->hasRequirement($variable)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Builds sample paths and hosts accepted by the route, or returns null when its requirements cannot be satisfied.
     *
     * @return list<array{string, string|null}>|null
     */
    private function generateSamples(Route $route): ?array
    {
        $compiled = $route->compile();
        $candidates = [];

        foreach ($compiled->getVariables() as $variable) {
            $default = $route->getDefault($variable);
            $requirement = $route->getRequirement($variable);
            $values = [];

            foreach (\is_scalar($default) && '' !== (string) $default ? [(string) $default, ...self::SAMPLE_VALUES] : self::SAMPLE_VALUES as $value) {
                if (null === $requirement || preg_match('{^(?:'.$requirement.')$}sD'.($route->getOption('utf8') ? 'u' : ''), $value)) {
                    $values[$value] = $value;
                }
            }

            if (!$values) {
                return null;
            }

            $candidates[$variable] = array_values($values);
        }

        $samples = [];

        for ($i = 0, $count = max([1, ...array_map(\count(...), $candidates)]); $i < $count; ++$i) {
            $parameters = [];
            foreach ($candidates as $variable => $values) {
                $parameters[$variable] = $values[$i % \count($values)];
            }

            $path = $this->fill($route->getPath(), $parameters);
            $host = '' !== $route->getHost() ? $this->fill($route->getHost(), $parameters) : null;

            if (preg_match($compiled->getRegex(), $path) && (null === $host || preg_match($compiled->getHostRegex(), $host))) {
                $samples[$path."\0".$host] = [$path, $host];
            }
        }

        return $samples ? array_values($samples) : null;
    }

    private function fill(string $pattern, array $parameters): string
    {
        return (string) preg_replace_callback('#\{!?([\w\x80-\xFF]+)\}#', static fn ($m) => $parameters[$m[1]] ?? $m[0], $pattern);
    }

    /**
     * Adds HEAD to the methods that allow GET, as the matcher does.
     *
     * @param string[] $methods
     *
     * @return string[]
     */
    private function expandMethods(array $methods): array
    {
        if (\in_array('GET', $methods, true) && !\in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        return $methods;
    }

    /**
     * @param string[] $values
     *
     * @return string[]
     */
    private function sort(array $values): array
    {
        sort($values);

        return $values;
    }
}
