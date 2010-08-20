<?php

namespace Symfony\Component\DependencyInjection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * ContainerInterface is the interface implemented by service container classes.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface ContainerInterface
{
    const EXCEPTION_ON_INVALID_REFERENCE = 1;
    const NULL_ON_INVALID_REFERENCE      = 2;
    const IGNORE_ON_INVALID_REFERENCE    = 3;

    /**
     * Sets a service.
     *
     * @param string $id      The service identifier
     * @param object $service The service instance
     */
    public function set($id, $service);

    /**
     * Gets a service.
     *
     * @param  string $id              The service identifier
     * @param  int    $invalidBehavior The behavior when the service does not exist
     *
     * @return object The associated service
     *
     * @throws \InvalidArgumentException if the service is not defined
     *
     * @see Reference
     */
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE);

    /**
     * Returns true if the given service is defined.
     *
     * @param  string  $id      The service identifier
     *
     * @return Boolean true if the service is defined, false otherwise
     */
    public function has($id);
}
