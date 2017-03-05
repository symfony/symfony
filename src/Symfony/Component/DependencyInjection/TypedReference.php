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
 *
 * @experimental in version 3.3
 */
class TypedReference extends Reference
{
    private $type;
    private $canBeAutoregistered;

    /**
     * @param string $id                  The service identifier
     * @param string $type                The PHP type of the identified service
     * @param int    $invalidBehavior     The behavior when the service does not exist
     * @param bool   $canBeAutoregistered Whether autowiring can autoregister the referenced service when it's a FQCN or not
     */
    public function __construct($id, $type, $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $canBeAutoregistered = true)
    {
        parent::__construct($id, $invalidBehavior);
        $this->type = $type;
        $this->canBeAutoregistered = $canBeAutoregistered;
    }

    public function getType()
    {
        return $this->type;
    }

    public function canBeAutoregistered()
    {
        return $this->canBeAutoregistered;
    }
}
