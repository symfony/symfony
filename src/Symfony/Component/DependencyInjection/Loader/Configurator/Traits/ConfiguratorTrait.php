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

trait ConfiguratorTrait
{
    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @param string|array $configurator A PHP callable reference
     *
     * @return $this
     */
    final public function configurator($configurator): self
    {
        $this->definition->setConfigurator(static::processValue($configurator, true));

        return $this;
    }
}
