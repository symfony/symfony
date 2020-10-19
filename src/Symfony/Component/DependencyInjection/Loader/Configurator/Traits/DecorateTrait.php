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

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

trait DecorateTrait
{
    /**
     * Sets the service that this service is decorating.
     *
     * @param string|null $id              The decorated service id, use null to remove decoration
     * @param string|null $renamedId       The new decorated service id
     * @param int         $priority        The priority of decoration
     * @param int         $invalidBehavior The behavior to adopt when decorated is invalid
     *
     * @return $this
     *
     * @throws InvalidArgumentException in case the decorated service id and the new decorated service id are equals
     */
    final public function decorate(?string $id, string $renamedId = null, int $priority = 0, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE): self
    {
        $this->definition->setDecoratedService($id, $renamedId, $priority, $invalidBehavior);

        return $this;
    }
}
