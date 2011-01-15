<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

/**
 * Parameter represents a parameter reference.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
