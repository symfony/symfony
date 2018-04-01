<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection;

/**
 * Parameter represents a parameter reference.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class Parameter
{
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    /**
     * @return string The parameter key
     */
    public function __toString()
    {
        return $this->id;
    }
}
