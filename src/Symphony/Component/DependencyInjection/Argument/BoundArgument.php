<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Argument;

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
final class BoundArgument implements ArgumentInterface
{
    private static $sequence = 0;

    private $value;
    private $identifier;
    private $used;

    public function __construct($value)
    {
        $this->value = $value;
        $this->identifier = ++self::$sequence;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return array($this->value, $this->identifier, $this->used);
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(array $values)
    {
        list($this->value, $this->identifier, $this->used) = $values;
    }
}
