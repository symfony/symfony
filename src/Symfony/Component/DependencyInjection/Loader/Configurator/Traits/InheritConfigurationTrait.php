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

trait InheritConfigurationTrait
{
    /**
     * Sets whether instanceof conditionals should be applied to the current definition.
     *
     * @return $this
     */
    final public function inheritConfiguration(bool $inheritConfiguration = true): static
    {
        $this->definition->setInheritConfiguration($inheritConfiguration);

        return $this;
    }
}
