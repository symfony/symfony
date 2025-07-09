<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonPath\Functions;

use Psr\Container\ContainerInterface;
use Symfony\Component\JsonPath\Exception\InvalidArgumentException;
use Symfony\Component\JsonPath\Exception\UnknownJsonPathFunctionException;

/**
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
final class JsonPathFunctionsProvider implements JsonPathFunctionsProviderInterface
{
    /**
     * @var array<string, JsonPathFunctionInterface>
     */
    private array $functions = [];

    public function __construct(
        private readonly ?ContainerInterface $locator = null,
    ) {
        foreach ([
            'length' => LengthFunction::class,
            'count' => CountFunction::class,
            'match' => MatchFunction::class,
            'search' => SearchFunction::class,
            'value' => ValueFunction::class,
        ] as $name => $function) {
            if (!$this->locator?->has($name)) {
                $this->functions[$name] = $function;
            } else {
                throw new InvalidArgumentException(\sprintf('Function "%s" is already registered.', $name));
            }
        }
    }

    public function hasFunction(string $name): bool
    {
        return isset($this->functions[$name])
            || $this->locator?->has($name);
    }

    public function getFunction(string $name): JsonPathFunctionInterface
    {
        if ($function = $this->functions[$name] ?? null) {
            return \is_string($function) ? $this->functions[$name] = new $function() : $function;
        }

        if ($this->locator?->has($name)) {
            return $this->locator->get($name);
        }

        throw new UnknownJsonPathFunctionException($name);
    }
}
