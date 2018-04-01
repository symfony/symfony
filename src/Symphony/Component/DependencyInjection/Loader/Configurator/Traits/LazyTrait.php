<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Loader\Configurator\Traits;

trait LazyTrait
{
    /**
     * Sets the lazy flag of this service.
     *
     * @return $this
     */
    final public function lazy(bool $lazy = true)
    {
        $this->definition->setLazy($lazy);

        return $this;
    }
}
