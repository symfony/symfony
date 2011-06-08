<?php

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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
