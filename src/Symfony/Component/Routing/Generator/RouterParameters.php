<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Generator;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final class RouterParameters
{
    /** @var array<string, array<int|string, RoutableInterface|string|int|null>> */
    private array $parameters = [];

    /**
     * The constructors take the default parameters which are used to fulfill the requirements for all routes which
     * are not specified using the `add` method.
     *
     * @param array<int|string, RoutableInterface|string|int|null> $defaultParameters
     */
    public function __construct(
        private readonly array $defaultParameters,
    ) {}

    /**
     * Using this method, one can define specific routing parameters for one or more route names. This is useful when
     * this `RoutableInterface` instance is used to build the parameters for a parent object. When the many-to-one
     * relation Match -> Pool exists, it would be possible to build parameters for the Pool detail page, by providing a
     * Match entity like `->generate('pool_details', $match)`.
     *
     * @param string|array<string>                        $routeNames
     * @param array<int|string, RoutableInterface|string|int|null> $parameters
     */
    public function add(string|array $routeNames, array $parameters): self
    {
        if (is_array($routeNames)) {
            foreach ($routeNames as $route) {
                $this->parameters[$route] = $parameters;
            }
        } else {
            $this->parameters[$routeNames] = $parameters;
        }

        return $this;
    }

    /**
     * @return array<int|string, string|int|null>
     */
    public function getParameters(string $route): array
    {
        $result = [];

        foreach ($this->parameters[$route] ?? $this->defaultParameters as $key => $value) {
            if ($value instanceof RoutableInterface) {
                $result = array_replace($result, $value->getRouterParameters()->getParameters($route));
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
