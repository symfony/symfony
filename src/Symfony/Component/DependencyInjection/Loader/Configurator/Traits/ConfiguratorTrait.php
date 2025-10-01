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

use Symfony\Component\DependencyInjection\Loader\Configurator\ReferenceConfigurator;

trait ConfiguratorTrait
{
    /**
     * Sets a configurator to call after the service is fully initialized.
     *
     * @return $this
     */
    final public function configurator(string|array|\Closure|ReferenceConfigurator $configurator): static
    {
        if ($configurator instanceof \Closure) {
            $this->definition->setConfigurator(static::processClosure($configurator));

            return $this;
        }

        $this->definition->setConfigurator(static::processValue($configurator, true));

        return $this;
    }
}
