<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents a PHP constant and its value.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ConstStub extends Stub
{
    public function __construct($name, $value)
    {
        $this->class = $name;
        $this->value = $value;
    }

    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Creates ConstStub from bitfield flag.
     *
     * @param int    $value  The value of bitfield flag
     * @param string $prefix The prefix filter applied on constant names
     *
     * @return self
     */
    public static function fromFlag($value, $prefix)
    {
        $constants = get_defined_constants();
        foreach ($constants as $c => $v) {
            // checks prefix + single bit (power of 2) + flagged bit
            if ('' !== $prefix && 0 !== strpos($c, $prefix) || 0 !== ($v & ($v - 1)) || ($value & $v) !== $v) {
                unset($constants[$c]);
            }
        }

        // checks extra bits
        if (array_sum($constants) !== $value) {
            for ($i = 0; ($v = 1 << $i) <= $value; ++$i) {
                if (($value & $v) === $v && !in_array($v, $constants)) {
                    $constants[$v] = $v;
                }
            }
        }

        asort($constants);
        $name = implode(' | ', array_keys($constants)) ?: 0;

        return new self($name, $value);
    }
}
