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

trait PublicTrait
{
    /**
     * @return $this
     */
    final public function public()
    {
        $this->definition->setPublic(true);

        return $this;
    }

    /**
     * @return $this
     */
    final public function private()
    {
        $this->definition->setPublic(false);

        return $this;
    }
}
