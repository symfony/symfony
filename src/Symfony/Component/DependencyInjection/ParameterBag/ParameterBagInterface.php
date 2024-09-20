<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\ParameterBag;

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * ParameterBagInterface is the interface implemented by objects that manage service container parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ParameterBagInterface
{
    /**
     * Clears all parameters.
     *
     * @throws LogicException if the ParameterBagInterface cannot be cleared
     */
    public function clear(): void;

    /**
     * Adds parameters to the service container parameters.
     *
     * @throws LogicException if the parameter cannot be added
     */
    public function add(array $parameters): void;

    /**
     * Gets the service container parameters.
     */
    public function all(): array;

    /**
     * Gets a service container parameter.
     *
     * @throws ParameterNotFoundException if the parameter is not defined
     */
    public function get(string $name): array|bool|string|int|float|\UnitEnum|null;

    /**
     * Removes a parameter.
     */
    public function remove(string $name): void;

    /**
     * Sets a service container parameter.
     *
     * @throws LogicException if the parameter cannot be set
     */
    public function set(string $name, array|bool|string|int|float|\UnitEnum|null $value): void;

    /**
     * Returns true if a parameter name is defined.
     */
    public function has(string $name): bool;

    /**
     * Replaces parameter placeholders (%name%) by their values for all parameters.
     */
    public function resolve(): void;

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     */
    public function resolveValue(mixed $value): mixed;

    /**
     * Escape parameter placeholders %.
     */
    public function escapeValue(mixed $value): mixed;

    /**
     * Unescape parameter placeholders %.
     */
    public function unescapeValue(mixed $value): mixed;
}
