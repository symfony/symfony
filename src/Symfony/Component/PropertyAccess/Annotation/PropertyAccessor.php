<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Annotation;

/**
 * Property accessor configuration annotation.
 *
 * @Annotation
 *
 * @author Luis Ramón López <lrlopez@gmail.com>
 */
class PropertyAccessor extends ConfigurationAnnotation
{
    protected $setter;

    protected $getter;

    protected $adder;

    protected $remover;

    public function getSetter()
    {
        return $this->setter;
    }

    public function setSetter($setter)
    {
        $this->setter = $setter;
    }

    public function getGetter()
    {
        return $this->getter;
    }

    public function setGetter($getter)
    {
        $this->getter = $getter;
    }

    public function getAdder()
    {
        return $this->adder;
    }

    public function setAdder($adder)
    {
        $this->adder = $adder;
    }

    public function getRemover()
    {
        return $this->remover;
    }

    public function setRemover($remover)
    {
        $this->remover = $remover;
    }
}
