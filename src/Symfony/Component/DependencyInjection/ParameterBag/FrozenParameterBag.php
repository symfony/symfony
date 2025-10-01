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

/**
 * Holds read-only parameters.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class FrozenParameterBag extends ParameterBag
{
    /**
     * For performance reasons, the constructor assumes that
     * all keys are already lowercased.
     *
     * This is always the case when used internally.
     */
    public function __construct(
        array $parameters = [],
        protected array $deprecatedParameters = [],
        protected array $nonEmptyParameters = [],
    ) {
        $this->parameters = $parameters;
        $this->resolved = true;
    }

    public function clear(): never
    {
        throw new LogicException('Impossible to call clear() on a frozen ParameterBag.');
    }

    public function add(array $parameters): never
    {
        throw new LogicException('Impossible to call add() on a frozen ParameterBag.');
    }

    public function set(string $name, array|bool|string|int|float|\UnitEnum|null $value): never
    {
        throw new LogicException('Impossible to call set() on a frozen ParameterBag.');
    }

    public function deprecate(string $name, string $package, string $version, string $message = 'The parameter "%s" is deprecated.'): never
    {
        throw new LogicException('Impossible to call deprecate() on a frozen ParameterBag.');
    }

    public function cannotBeEmpty(string $name, string $message = 'A non-empty parameter "%s" is required.'): never
    {
        throw new LogicException('Impossible to call cannotBeEmpty() on a frozen ParameterBag.');
    }

    public function remove(string $name): never
    {
        throw new LogicException('Impossible to call remove() on a frozen ParameterBag.');
    }
}
