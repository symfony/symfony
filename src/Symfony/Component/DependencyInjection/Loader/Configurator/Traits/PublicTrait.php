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

trait PublicTrait
{
    /**
     * @return $this
     */
    final public function public(): self
    {
        $this->definition->setPublic(true);

        return $this;
    }

    /**
     * @return $this
     */
    final public function private(): self
    {
        $this->definition->setPublic(false);

        return $this;
    }
}
