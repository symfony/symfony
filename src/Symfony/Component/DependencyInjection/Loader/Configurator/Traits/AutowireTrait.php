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

trait AutowireTrait
{
    /**
     * Enables/disables autowiring.
     *
     * @return $this
     */
    final public function autowire(bool $autowired = true): static
    {
        $this->definition->setAutowired($autowired);

        return $this;
    }

    /**
     * Enables/disables autowiring of optional arguments.
     *
     * @return $this
     */
    final public function autowireOptionalParameters(bool $autowireOptionalParameters = true): static
    {
        $this->definition->setAutowireOptionalParameters($autowireOptionalParameters);

        return $this;
    }
}
