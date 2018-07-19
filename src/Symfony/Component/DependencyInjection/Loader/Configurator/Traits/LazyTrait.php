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

trait LazyTrait
{
    /**
     * Sets the lazy flag of this service.
     *
     * @param bool|string A FQCN to derivate the lazy proxy from or `true` to make it extend from the definition's class
     *
     * @return $this
     */
    final public function lazy($lazy = true)
    {
        $this->definition->setLazy((bool) $lazy);
        if (\is_string($lazy)) {
            $this->definition->addTag('proxy', array('interface' => $lazy));
        }

        return $this;
    }
}
