<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TypeHinted
{
    private $date;

    /**
     * @var \Countable
     */
    private $countable;

    public function setDate(\DateTime $date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        return $this->date;
    }

    /**
     * @return \Countable
     */
    public function getCountable()
    {
        return $this->countable;
    }

    /**
     * @param \Countable $countable
     */
    public function setCountable(\Countable $countable)
    {
        $this->countable = $countable;
    }
}
