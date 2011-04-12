<?php

namespace Symfony\Component\DependencyInjection;

/**
 * Scope class.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Scope implements ScopeInterface
{
    private $name;
    private $parentName;

    public function __construct($name, $parentName = ContainerInterface::SCOPE_CONTAINER)
    {
        $this->name = $name;
        $this->parentName = $parentName;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getParentName()
    {
        return $this->parentName;
    }
}