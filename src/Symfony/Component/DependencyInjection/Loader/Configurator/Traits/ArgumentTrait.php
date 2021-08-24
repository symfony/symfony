<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator\Traits;

trait ArgumentTrait
{
    /**
     * Sets the arguments to pass to the service constructor/factory method.
     *
     * @return $this
     */
    final public function args(array $arguments): static
    {
        $this->definition->setArguments(static::processValue($arguments, true));

        return $this;
    }

    /**
     * Sets one argument to pass to the service constructor/factory method.
     *
     * @return $this
     */
    final public function arg(string|int $key, mixed $value): static
    {
        $this->definition->setArgument($key, static::processValue($value, true));

        return $this;
    }
}
