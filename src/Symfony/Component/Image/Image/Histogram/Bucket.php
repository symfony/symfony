<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Histogram;

/**
 * Bucket histogram
 */
final class Bucket implements \Countable
{
    /**
     * @var Range
     */
    private $range;

    /**
     * @var integer
     */
    private $count;

    /**
     * @param Range   $range
     * @param integer $count
     */
    public function __construct(Range $range, $count = 0)
    {
        $this->range = $range;
        $this->count = $count;
    }

    /**
     * @param integer $value
     */
    public function add($value)
    {
        if ($this->range->contains($value)) {
            $this->count++;
        }
    }

    /**
     * @return integer The number of elements in the bucket.
     */
    public function count()
    {
        return $this->count;
    }
}
