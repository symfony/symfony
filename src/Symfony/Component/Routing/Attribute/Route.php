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
    private ?string $path = null;
    private array $localizedPaths = [];
    private array $methods;
    private array $schemes;

    /**
     * @param string|array<string,string>|null $path         The route path (i.e. "/user/login")
     * @param string|null                      $name         The route name (i.e. "app_user_login")
     * @param array<string|\Stringable>        $requirements Requirements for the route attributes, @see https://symfony.com/doc/current/routing.html#parameters-validation
     * @param array<string, mixed>             $options      Options for the route (i.e. ['prefix' => '/api'])
     * @param array<string, mixed>             $defaults     Default values for the route attributes and query parameters
     * @param string|null                      $host         The host for which this route should be active (i.e. "localhost")
     * @param string|string[]                  $methods      The list of HTTP methods allowed by this route
     * @param string|string[]                  $schemes      The list of schemes allowed by this route (i.e. "https")
     * @param string|null                      $condition    An expression that must evaluate to true for the route to be matched, @see https://symfony.com/doc/current/routing.html#matching-expressions
     * @param int|null                         $priority     The priority of the route if multiple ones are defined for the same path
     * @param string|null                      $locale       The locale accepted by the route
     * @param string|null                      $format       The format returned by the route (i.e. "json", "xml")
     * @param bool|null                        $utf8         Whether the route accepts UTF-8 in its parameters
     * @param bool|null                        $stateless    Whether the route is defined as stateless or stateful, @see https://symfony.com/doc/current/routing.html#stateless-routes
     * @param string|null                      $env          The env in which the route is defined (i.e. "dev", "test", "prod")
     */
    public function __construct(
        string|array|null $path = null,
        private ?string $name = null,
        private array $requirements = [],
        private array $options = [],
        private array $defaults = [],
        private ?string $host = null,
        array|string $methods = [],
        array|string $schemes = [],
        private ?string $condition = null,
        private ?int $priority = null,
        ?string $locale = null,
        ?string $format = null,
        ?bool $utf8 = null,
        ?bool $stateless = null,
        private ?string $env = null,
    ) {
        if (\is_array($path)) {
            $this->localizedPaths = $path;
        } else {
            $this->path = $path;
        }
        $this->setMethods($methods);
        $this->setSchemes($schemes);

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

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setLocalizedPaths(array $localizedPaths): void
    {
        $this->localizedPaths = $localizedPaths;
    }

    public function getLocalizedPaths(): array
    {
        return $this->localizedPaths;
    }

    public function setHost(string $pattern): void
    {
        $this->host = $pattern;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setRequirements(array $requirements): void
    {
        $this->requirements = $requirements;
    }

    public function getRequirements(): array
    {
        return $this->requirements;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setDefaults(array $defaults): void
    {
        $this->defaults = $defaults;
    }

    public function getDefaults(): array
    {
        return $this->defaults;
    }

    public function setSchemes(array|string $schemes): void
    {
        $this->schemes = (array) $schemes;
    }

    public function getSchemes(): array
    {
        return $this->schemes;
    }

    public function setMethods(array|string $methods): void
    {
        $this->methods = (array) $methods;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function setCondition(?string $condition): void
    {
        $this->condition = $condition;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setEnv(?string $env): void
    {
        $this->env = $env;
    }

    public function getEnv(): ?string
    {
        return $this->env;
    }
}

if (!class_exists(\Symfony\Component\Routing\Annotation\Route::class, false)) {
    class_alias(Route::class, \Symfony\Component\Routing\Annotation\Route::class);
}
