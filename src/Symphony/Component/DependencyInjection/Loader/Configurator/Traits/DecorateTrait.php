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

use Symphony\Component\DependencyInjection\Exception\InvalidArgumentException;

trait DecorateTrait
{
    /**
     * Sets the service that this service is decorating.
     *
     * @param null|string $id        The decorated service id, use null to remove decoration
     * @param null|string $renamedId The new decorated service id
     * @param int         $priority  The priority of decoration
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    final public function decorate($id, $renamedId = null, $priority = 0)
    {
        $this->definition->setDecoratedService($id, $renamedId, $priority);

        return $this;
    }
}
