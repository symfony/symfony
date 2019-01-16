<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Dumper;

use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * PhpMatcherDumper creates a PHP class able to match URLs for a given set of routes.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 * @author Nicolas Grekas <p@tchwork.com>
 */
class PhpMatcherDumper extends MatcherDumper
{
    private $expressionLanguage;
    private $signalingException;

    /**
     * @var ExpressionFunctionProviderInterface[]
     */
    private $expressionLanguageProviders = [];

    /**
     * Dumps a set of routes to a PHP class.
     *
     * Available options:
     *
     *  * class:      The class name
     *  * base_class: The base class name
     *
     * @param array $options An array of options
     *
     * @return string A PHP class representing the matcher class
     */
    public function dump(array $options = [])
    {
        $options = array_replace([
            'class' => 'ProjectUrlMatcher',
            'base_class' => 'Symfony\\Component\\Routing\\Matcher\\UrlMatcher',
        ], $options);

        // trailing slash support is only enabled if we know how to redirect the user
        $interfaces = class_implements($options['base_class']);

        return <<<EOF
<?php

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherTrait;
use Symfony\Component\Routing\RequestContext;

/**
 * This class has been auto-generated
 * by the Symfony Routing Component.
 */
class {$options['class']} extends {$options['base_class']}
{
    use PhpMatcherTrait;

    public function __construct(RequestContext \$context)
    {
        \$this->context = \$context;
{$this->generateProperties()}    }
}

EOF;
    }

    public function addExpressionLanguageProvider(ExpressionFunctionProviderInterface $provider)
    {
        $this->expressionLanguageProviders[] = $provider;
    }

    /**
     * Generates the code for the match method implementing UrlMatcherInterface.
     */
    private function generateProperties(): string
    {
        // Group hosts by same-suffix, re-order when possible
        $matchHost = false;
        $routes = new StaticPrefixCollection();
        foreach ($this->getRoutes()->all() as $name => $route) {
            if ($host = $route->getHost()) {
                $matchHost = true;
                $host = '/'.strtr(strrev($host), '}.{', '(/)');
            }

            $routes->addRoute($host ?: '/(.*)', [$name, $route]);
        }

        if ($matchHost) {
            $code = '$this->matchHost = true;'."\n";
            $routes = $routes->populateCollection(new RouteCollection());
        } else {
            $code = '';
            $routes = $this->getRoutes();
        }

        list($staticRoutes, $dynamicRoutes) = $this->groupStaticRoutes($routes);

        $conditions = [null];
        $code .= $this->compileStaticRoutes($staticRoutes, $conditions);
        $chunkLimit = \count($dynamicRoutes);

        while (true) {
            try {
                $this->signalingException = new \RuntimeException('preg_match(): Compilation failed: regular expression is too large');
                $code .= $this->compileDynamicRoutes($dynamicRoutes, $matchHost, $chunkLimit, $conditions);
                break;
            } catch (\Exception $e) {
                if (1 < $chunkLimit && $this->signalingException === $e) {
                    $chunkLimit = 1 + ($chunkLimit >> 1);
                    continue;
                }
                throw $e;
            }
        }

        unset($conditions[0]);

        if (!$conditions) {
            return $this->indent($code, 2);
        }

        foreach ($conditions as $expression => $condition) {
            $conditions[$expression] = "case {$condition}: return {$expression};";
        }

        return $this->indent($code, 2).<<<EOF
        \$this->checkCondition = static function (\$condition, \$context, \$request) {
            switch (\$condition) {
{$this->indent(implode("\n", $conditions), 4)}
            }
        };

EOF;
    }

    /**
     * Splits static routes from dynamic routes, so that they can be matched first, using a simple switch.
     */
    private function groupStaticRoutes(RouteCollection $collection): array
    {
        $staticRoutes = $dynamicRegex = [];
        $dynamicRoutes = new RouteCollection();

        foreach ($collection->all() as $name => $route) {
            $compiledRoute = $route->compile();
            $hostRegex = $compiledRoute->getHostRegex();
            $regex = $compiledRoute->getRegex();
            if ($hasTrailingSlash = '/' !== $route->getPath()) {
                $pos = strrpos($regex, '$');
                $hasTrailingSlash = '/' === $regex[$pos - 1];
                $regex = substr_replace($regex, '/?$', $pos - $hasTrailingSlash, 1 + $hasTrailingSlash);
            }

            if (!$compiledRoute->getPathVariables()) {
                $host = !$compiledRoute->getHostVariables() ? $route->getHost() : '';
                $url = $route->getPath();
                if ($hasTrailingSlash) {
                    $url = substr($url, 0, -1);
                }
                foreach ($dynamicRegex as list($hostRx, $rx)) {
                    if (preg_match($rx, $url) && (!$host || !$hostRx || preg_match($hostRx, $host))) {
                        $dynamicRegex[] = [$hostRegex, $regex];
                        $dynamicRoutes->add($name, $route);
                        continue 2;
                    }
                }

                $staticRoutes[$url][$name] = [$route, $hasTrailingSlash];
            } else {
                $dynamicRegex[] = [$hostRegex, $regex];
                $dynamicRoutes->add($name, $route);
            }
        }

        return [$staticRoutes, $dynamicRoutes];
    }

    /**
     * Compiles static routes in a switch statement.
     *
     * Condition-less paths are put in a static array in the switch's default, with generic matching logic.
     * Paths that can match two or more routes, or have user-specified conditions are put in separate switch's cases.
     *
     * @throws \LogicException
     */
    private function compileStaticRoutes(array $staticRoutes, array &$conditions): string
    {
        if (!$staticRoutes) {
            return '';
        }
        $code = '';

        foreach ($staticRoutes as $url => $routes) {
            $code .= self::export($url)." => [\n";
            foreach ($routes as $name => list($route, $hasTrailingSlash)) {
                $code .= $this->compileRoute($route, $name, !$route->compile()->getHostVariables() ? $route->getHost() : $route->compile()->getHostRegex() ?: null, $hasTrailingSlash, false, $conditions);
            }
            $code .= "],\n";
        }

        if ($code) {
            return "\$this->staticRoutes = [\n{$this->indent($code, 1)}];\n";
        }

        return $code;
    }

    /**
     * Compiles a regular expression followed by a switch statement to match dynamic routes.
     *
     * The regular expression matches both the host and the pathinfo at the same time. For stellar performance,
     * it is built as a tree of patterns, with re-ordering logic to group same-prefix routes together when possible.
     *
     * Patterns are named so that we know which one matched (https://pcre.org/current/doc/html/pcre2syntax.html#SEC23).
     * This name is used to "switch" to the additional logic required to match the final route.
     *
     * Condition-less paths are put in a static array in the switch's default, with generic matching logic.
     * Paths that can match two or more routes, or have user-specified conditions are put in separate switch's cases.
     *
     * Last but not least:
     *  - Because it is not possibe to mix unicode/non-unicode patterns in a single regexp, several of them can be generated.
     *  - The same regexp can be used several times when the logic in the switch rejects the match. When this happens, the
     *    matching-but-failing subpattern is blacklisted by replacing its name by "(*F)", which forces a failure-to-match.
     *    To ease this backlisting operation, the name of subpatterns is also the string offset where the replacement should occur.
     */
    private function compileDynamicRoutes(RouteCollection $collection, bool $matchHost, int $chunkLimit, array &$conditions): string
    {
        if (!$collection->all()) {
            return '';
        }
        $code = '';
        $state = (object) [
            'regex' => '',
            'routes' => '',
            'mark' => 0,
            'markTail' => 0,
            'hostVars' => [],
            'vars' => [],
        ];
        $state->getVars = static function ($m) use ($state) {
            if ('_route' === $m[1]) {
                return '?:';
            }

            $state->vars[] = $m[1];

            return '';
        };

        $chunkSize = 0;
        $prev = null;
        $perModifiers = [];
        foreach ($collection->all() as $name => $route) {
            preg_match('#[a-zA-Z]*$#', $route->compile()->getRegex(), $rx);
            if ($chunkLimit < ++$chunkSize || $prev !== $rx[0] && $route->compile()->getPathVariables()) {
                $chunkSize = 1;
                $routes = new RouteCollection();
                $perModifiers[] = [$rx[0], $routes];
                $prev = $rx[0];
            }
            $routes->add($name, $route);
        }

        foreach ($perModifiers as list($modifiers, $routes)) {
            $prev = false;
            $perHost = [];
            foreach ($routes->all() as $name => $route) {
                $regex = $route->compile()->getHostRegex();
                if ($prev !== $regex) {
                    $routes = new RouteCollection();
                    $perHost[] = [$regex, $routes];
                    $prev = $regex;
                }
                $routes->add($name, $route);
            }
            $prev = false;
            $rx = '{^(?';
            $code .= "\n    {$state->mark} => ".self::export($rx);
            $state->mark += \strlen($rx);
            $state->regex = $rx;

            foreach ($perHost as list($hostRegex, $routes)) {
                if ($matchHost) {
                    if ($hostRegex) {
                        preg_match('#^.\^(.*)\$.[a-zA-Z]*$#', $hostRegex, $rx);
                        $state->vars = [];
                        $hostRegex = '(?i:'.preg_replace_callback('#\?P<([^>]++)>#', $state->getVars, $rx[1]).')\.';
                        $state->hostVars = $state->vars;
                    } else {
                        $hostRegex = '(?:(?:[^./]*+\.)++)';
                        $state->hostVars = [];
                    }
                    $state->mark += \strlen($rx = ($prev ? ')' : '')."|{$hostRegex}(?");
                    $code .= "\n        .".self::export($rx);
                    $state->regex .= $rx;
                    $prev = true;
                }

                $tree = new StaticPrefixCollection();
                foreach ($routes->all() as $name => $route) {
                    preg_match('#^.\^(.*)\$.[a-zA-Z]*$#', $route->compile()->getRegex(), $rx);

                    $state->vars = [];
                    $regex = preg_replace_callback('#\?P<([^>]++)>#', $state->getVars, $rx[1]);
                    if ($hasTrailingSlash = '/' !== $regex && '/' === $regex[-1]) {
                        $regex = substr($regex, 0, -1);
                    }
                    $hasTrailingVar = (bool) preg_match('#\{\w+\}/?$#', $route->getPath());

                    $tree->addRoute($regex, [$name, $regex, $state->vars, $route, $hasTrailingSlash, $hasTrailingVar]);
                }

                $code .= $this->compileStaticPrefixCollection($tree, $state, 0, $conditions);
            }
            if ($matchHost) {
                $code .= "\n        .')'";
                $state->regex .= ')';
            }
            $rx = ")/?$}{$modifiers}";
            $code .= "\n        .'{$rx}',";
            $state->regex .= $rx;
            $state->markTail = 0;

            // if the regex is too large, throw a signaling exception to recompute with smaller chunk size
            set_error_handler(function ($type, $message) { throw 0 === strpos($message, $this->signalingException->getMessage()) ? $this->signalingException : new \ErrorException($message); });
            try {
                preg_match($state->regex, '');
            } finally {
                restore_error_handler();
            }
        }

        unset($state->getVars);

        return "\$this->regexpList = [{$code}\n];\n"
            ."\$this->dynamicRoutes = [\n{$this->indent($state->routes, 1)}];\n";
    }

    /**
     * Compiles a regexp tree of subpatterns that matches nested same-prefix routes.
     *
     * @param \stdClass $state A simple state object that keeps track of the progress of the compilation,
     *                         and gathers the generated switch's "case" and "default" statements
     */
    private function compileStaticPrefixCollection(StaticPrefixCollection $tree, \stdClass $state, int $prefixLen, array &$conditions): string
    {
        $code = '';
        $prevRegex = null;
        $routes = $tree->getRoutes();

        foreach ($routes as $i => $route) {
            if ($route instanceof StaticPrefixCollection) {
                $prevRegex = null;
                $prefix = substr($route->getPrefix(), $prefixLen);
                $state->mark += \strlen($rx = "|{$prefix}(?");
                $code .= "\n            .".self::export($rx);
                $state->regex .= $rx;
                $code .= $this->indent($this->compileStaticPrefixCollection($route, $state, $prefixLen + \strlen($prefix), $conditions));
                $code .= "\n            .')'";
                $state->regex .= ')';
                ++$state->markTail;
                continue;
            }

            list($name, $regex, $vars, $route, $hasTrailingSlash, $hasTrailingVar) = $route;
            $compiledRoute = $route->compile();
            $vars = array_merge($state->hostVars, $vars);

            if ($compiledRoute->getRegex() === $prevRegex) {
                $state->routes = substr_replace($state->routes, $this->compileRoute($route, $name, $vars, $hasTrailingSlash, $hasTrailingVar, $conditions), -3, 0);
                continue;
            }

            $state->mark += 3 + $state->markTail + \strlen($regex) - $prefixLen;
            $state->markTail = 2 + \strlen($state->mark);
            $rx = sprintf('|%s(*:%s)', substr($regex, $prefixLen), $state->mark);
            $code .= "\n            .".self::export($rx);
            $state->regex .= $rx;

            $prevRegex = $compiledRoute->getRegex();
            $state->routes .= sprintf("%s => [\n%s],\n", $state->mark, $this->compileRoute($route, $name, $vars, $hasTrailingSlash, $hasTrailingVar, $conditions));
        }

        return $code;
    }

    /**
     * Compiles a single Route to PHP code used to match it against the path info.
     */
    private function compileRoute(Route $route, string $name, $vars, bool $hasTrailingSlash, bool $hasTrailingVar, array &$conditions): string
    {
        $defaults = $route->getDefaults();

        if (isset($defaults['_canonical_route'])) {
            $name = $defaults['_canonical_route'];
            unset($defaults['_canonical_route']);
        }

        if ($condition = $route->getCondition()) {
            $condition = $this->getExpressionLanguage()->compile($condition, ['context', 'request']);
            $condition = $conditions[$condition] ?? $conditions[$condition] = (false !== strpos($condition, '$request') ? 1 : -1) * \count($conditions);
        } else {
            $condition = 'null';
        }

        return sprintf(
            "    [%s, %s, %s, %s, %s, %s, %s],\n",
            self::export(['_route' => $name] + $defaults),
            self::export($vars),
            self::export(array_flip($route->getMethods()) ?: null),
            self::export(array_flip($route->getSchemes()) ?: null),
            self::export($hasTrailingSlash),
            self::export($hasTrailingVar),
            $condition
        );
    }

    private function getExpressionLanguage()
    {
        if (null === $this->expressionLanguage) {
            if (!class_exists('Symfony\Component\ExpressionLanguage\ExpressionLanguage')) {
                throw new \LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
            }
            $this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
        }

        return $this->expressionLanguage;
    }

    private function indent($code, $level = 1)
    {
        $code = preg_replace('/ => \[\n    (\[.+),\n\],/', ' => [$1],', $code);

        return preg_replace('/^./m', str_repeat('    ', $level).'$0', $code);
    }

    /**
     * @internal
     */
    public static function export($value): string
    {
        if (null === $value) {
            return 'null';
        }
        if (!\is_array($value)) {
            if (\is_object($value)) {
                throw new \InvalidArgumentException('Symfony\Component\Routing\Route cannot contain objects.');
            }

            return str_replace("\n", '\'."\n".\'', var_export($value, true));
        }
        if (!$value) {
            return '[]';
        }

        $i = 0;
        $export = '[';

        foreach ($value as $k => $v) {
            if ($i === $k) {
                ++$i;
            } else {
                $export .= self::export($k).' => ';

                if (\is_int($k) && $i < $k) {
                    $i = 1 + $k;
                }
            }

            $export .= self::export($v).', ';
        }

        return substr_replace($export, ']', -2);
    }
}
