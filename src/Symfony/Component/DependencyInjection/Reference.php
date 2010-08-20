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
 * Reference represents a service reference.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Reference
{
    protected $id;
    protected $invalidBehavior;

    /**
     * Constructor.
     *
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @see Container
     */
    public function __construct($id, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $this->id = $id;
        $this->invalidBehavior = $invalidBehavior;
    }

    /**
     * __toString.
     *
     * @return string The service identifier
     */
    public function __toString()
    {
        return (string) $this->id;
    }

    public function getInvalidBehavior()
    {
        return $this->invalidBehavior;
    }
}
