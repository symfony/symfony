<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection;

/**
 * Reference represents a service reference.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class Reference
{
    private $id;
    private $invalidBehavior;

    public function __construct(string $id, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        $this->id = $id;
        $this->invalidBehavior = $invalidBehavior;
    }

    /**
     * @return string The service identifier
     */
    public function __toString()
    {
        return $this->id;
    }

    /**
     * Returns the behavior to be used when the service does not exist.
     *
     * @return int
     */
    public function getInvalidBehavior()
    {
        return $this->invalidBehavior;
    }
}
