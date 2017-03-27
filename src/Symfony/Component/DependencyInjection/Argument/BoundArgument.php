<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

/**
 * @author Guilhem Niot <guilhem.niot@gmail.com>
 */
final class BoundArgument implements ArgumentInterface
{
    private static $count = 0;

    private $value;
    private $identifier;

    public function __construct($value)
    {
        $this->value = $value;
        $this->identifier = ++self::$count;
    }

    /**
     * {@inheritdoc}
     */
    public function getValues()
    {
        return array($this->value, $this->identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function setValues(array $values)
    {
        list($this->value, $this->identifier) = $values;
    }
}
