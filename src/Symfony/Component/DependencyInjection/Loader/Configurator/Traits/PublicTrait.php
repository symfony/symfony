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
 * @method $this public()
 * @method $this private()
 */
trait PublicTrait
{
    /**
     * @return $this
     */
    final protected function setPublic()
    {
        $this->definition->setPublic(true);

        return $this;
    }

    /**
     * @return $this
     */
    final protected function setPrivate()
    {
        $this->definition->setPublic(false);

        return $this;
    }
}
