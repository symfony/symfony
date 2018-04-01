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

trait AbstractTrait
{
    /**
     * Whether this definition is abstract, that means it merely serves as a
     * template for other definitions.
     *
     * @return $this
     */
    final public function abstract(bool $abstract = true)
    {
        $this->definition->setAbstract($abstract);

        return $this;
    }
}
