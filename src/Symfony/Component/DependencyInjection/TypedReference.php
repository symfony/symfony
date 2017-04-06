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
 * Represents a PHP type-hinted service reference.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class TypedReference extends Reference
{
    private $type;
    private $requiringClass;

    /**
     * @param string $id              The service identifier
     * @param string $type            The PHP type of the identified service
     * @param string $requiringClass  The class of the service that requires the referenced type
     * @param int    $invalidBehavior The behavior when the service does not exist
     */
    public function __construct($id, $type, $requiringClass = '', $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE)
    {
        parent::__construct($id, $invalidBehavior);
        $this->type = $type;
        $this->requiringClass = $requiringClass;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getRequiringClass()
    {
        return $this->requiringClass;
    }

    public function canBeAutoregistered()
    {
        return $this->requiringClass && (false !== $i = strpos($this->type, '\\')) && 0 === strncasecmp($this->type, $this->requiringClass, 1 + $i);
    }
}
