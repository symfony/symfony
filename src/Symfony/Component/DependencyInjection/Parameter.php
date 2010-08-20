<?php

namespace Symfony\Component\DependencyInjection;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Parameter represents a parameter reference.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Parameter
{
    protected $id;

    /**
     * Constructor.
     *
     * @param string $id The parameter key
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * __toString.
     *
     * @return string The parameter key
     */
    public function __toString()
    {
        return (string) $this->id;
    }
}
