<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Attribute;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Alexander M. Turek <me@derrabus.de>
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Route
{
    /** @var string[] */
    public array $methods;

    /** @var string[] */
    public array $envs;

    /** @var string[] */
    public array $schemes;

    /** @var (string|DeprecatedAlias)[] */
    public array $aliases = [];

    /**
     * @param string|array<string,string>|null                  $path         The route path (i.e. "/user/login")
     * @param string|null                                       $name         The route name (i.e. "app_user_login")
     * @param array<string|\Stringable>                         $requirements Requirements for the route attributes, @see https://symfony.com/doc/current/routing.html#parameters-validation
     * @param array<string, mixed>                              $options      Options for the route (i.e. ['prefix' => '/api'])
     * @param array<string, mixed>                              $defaults     Default values for the route attributes and query parameters
     * @param string|null                                       $host         The host for which this route should be active (i.e. "localhost")
     * @param string|string[]                                   $methods      The list of HTTP methods allowed by this route
     * @param string|string[]                                   $schemes      The list of schemes allowed by this route (i.e. "https")
     * @param string|null                                       $condition    An expression that must evaluate to true for the route to be matched, @see https://symfony.com/doc/current/routing.html#matching-expressions
     * @param int|null                                          $priority     The priority of the route if multiple ones are defined for the same path
     * @param string|null                                       $locale       The locale accepted by the route
     * @param string|null                                       $format       The format returned by the route (i.e. "json", "xml")
     * @param bool|null                                         $utf8         Whether the route accepts UTF-8 in its parameters
     * @param bool|null                                         $stateless    Whether the route is defined as stateless or stateful, @see https://symfony.com/doc/current/routing.html#stateless-routes
     * @param string|string[]|null                              $env          The env(s) in which the route is defined (i.e. "dev", "test", "prod", ["dev", "test"])
     * @param string|DeprecatedAlias|(string|DeprecatedAlias)[] $alias        The list of aliases for this route
     */
    public function __construct(
        public string|array|null $path = null,
        public ?string $name = null,
        public array $requirements = [],
        public array $options = [],
        public array $defaults = [],
        public ?string $host = null,
        array|string $methods = [],
        array|string $schemes = [],
        public ?string $condition = null,
        public ?int $priority = null,
        ?string $locale = null,
        ?string $format = null,
        ?bool $utf8 = null,
        ?bool $stateless = null,
        string|array|null $env = null,
        string|DeprecatedAlias|array $alias = [],
    ) {
        $this->path = $path;
        $this->methods = (array) $methods;
        $this->schemes = (array) $schemes;
        $this->envs = (array) $env;
        $this->aliases = \is_array($alias) ? $alias : [$alias];

        if (null !== $locale) {
            $this->defaults['_locale'] = $locale;
        }

        if (null !== $format) {
            $this->defaults['_format'] = $format;
        }

        if (null !== $utf8) {
            $this->options['utf8'] = $utf8;
        }

        if (null !== $stateless) {
            $this->defaults['_stateless'] = $stateless;
        }
    }
}
