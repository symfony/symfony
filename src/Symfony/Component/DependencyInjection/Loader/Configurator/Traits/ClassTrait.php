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

/**
 * @method $this class(string $class)
 */
trait ClassTrait
{
    /**
     * Sets the service class.
     *
     * @param string $class The service class
     *
     * @return $this
     */
    final protected function setClass($class)
    {
        $this->definition->setClass($class);

        return $this;
    }
}
