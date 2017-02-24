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
 */
class Reference
{
    private $id;
    private $invalidBehavior;

    /**
     * @param string $id              The service identifier
     * @param int    $invalidBehavior The behavior when the service does not exist
     *
     * @see Container
     */
    public function __construct($id, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if (!is_string($id)) {
            $type = is_object($id) ? get_class($id) : gettype($id);
            $id = (string) $id;
            @trigger_error(sprintf('Non-string identifiers are deprecated since Symfony 3.3 and won\'t be supported in 4.0 for Reference to "%s" ("%s" given.) Cast it to string beforehand.', $id, $type), E_USER_DEPRECATED);
        }
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
