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
    private $name;
    private $requiringClass;

    /**
     * @param string $id              The service identifier
     * @param string $type            The PHP type of the identified service
     * @param int    $invalidBehavior The behavior when the service does not exist
     * @param string $name            The name of the argument targeting the service
     */
    public function __construct(string $id, string $type, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, string $name = null)
    {
        $this->name = $type === $id ? $name : null;
        parent::__construct($id, $invalidBehavior);
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
