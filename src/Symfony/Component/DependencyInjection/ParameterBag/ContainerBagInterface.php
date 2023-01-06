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

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * ContainerBagInterface is the interface implemented by objects that manage service container parameters.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface ContainerBagInterface extends ContainerInterface
{
    /**
     * Gets the service container parameters.
     */
    public function all(): array;

    /**
     * Replaces parameter placeholders (%name%) by their values.
     *
     * @template TValue of array<array|scalar>|scalar
     *
     * @param TValue $value
     *
     * @psalm-return (TValue is scalar ? array|scalar : array<array|scalar>)
     *
     * @throws ParameterNotFoundException if a placeholder references a parameter that does not exist
     */
    public function resolveValue(mixed $value);

    /**
     * Escape parameter placeholders %.
     */
    public function escapeValue(mixed $value): mixed;

    /**
     * Unescape parameter placeholders %.
     */
    public function unescapeValue(mixed $value): mixed;
}
