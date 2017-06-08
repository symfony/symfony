<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Flag;

use Symfony\Component\Flag\Exception\InvalidArgumentException;

/**
 * Concrete Flag class that handles bitfields.
 *
 * @see https://en.wikipedia.org/wiki/Bit_field
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
class Flag extends AbstractFlag
{
    /**
     * Sets bitfield value.
     *
     * @param int $bitfield Bitfield value
     *
     * @return $this
     *
     * @throws InvalidArgumentException When bitfield exceeds integer max limit
     * @throws InvalidArgumentException When bitfield is not an integer
     */
    public function set($bitfield)
    {
        if (PHP_INT_MAX < $bitfield) {
            throw new InvalidArgumentException('Bitfield must not exceed integer max limit.');
        }

        if (!is_int($bitfield)) {
            throw new InvalidArgumentException('Bitfield must be an integer.');
        }

        parent::set($bitfield);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function add($flag)
    {
        if (false === $this->from && !isset($this->flags[$flag])) {
            $this->flags[$flag] = $flag;
        }

        $this->set($this->bitfield | $flag);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($flag)
    {
        $this->set($this->bitfield & ~$flag);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function has($flags)
    {
        return ($this->bitfield & $flags) === $flags;
    }
}
