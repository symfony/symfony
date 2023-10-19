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
    private string $type;
    private ?string $name;
    private array $attributes;

    /**
     * @param string      $id              The service identifier
     * @param string      $type            The PHP type of the identified service
     * @param int         $invalidBehavior The behavior when the service does not exist
     * @param string|null $name            The name of the argument targeting the service
     * @param array       $attributes      The attributes to be used
     */
    public function __construct(string $id, string $type, int $invalidBehavior = ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, string $name = null, array $attributes = [])
    {
        $this->name = $type === $id ? $name : null;
        parent::__construct($id, $invalidBehavior);
        $this->type = $type;
        $this->attributes = $attributes;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
