<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * Reference represents a service reference.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class Reference
{
    private $id;
    private $invalidBehavior;
    private $strict;

    /**
     * Constructor.
     *
     * @param string  $id              The service identifier
     * @param int     $invalidBehavior The behavior when the service does not exist
     * @param Boolean $strict          Sets how this reference is validated
     *
     * @see Container
     */
    public function __construct($id, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $strict = true)
    {
        $this->id = strtolower($id);
        $this->invalidBehavior = $invalidBehavior;
        $this->strict = $strict;
    }

    /**
     * __toString.
     *
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

    /**
     * Returns true when this Reference is strict
     *
     * @return Boolean
     */
    public function isStrict()
    {
        return $this->strict;
    }
}
