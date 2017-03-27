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

use Symfony\Component\Image\Exception\OutOfBoundsException;

/**
 * Range histogram.
 */
final class Range
{
    /**
     * @var int
     */
    private $start;

    /**
     * @var int
     */
    private $end;

    /**
     * @param int $start
     * @param int $end
     *
     * @throws OutOfBoundsException
     */
    public function __construct($start, $end)
    {
        if ($end <= $start) {
            throw new OutOfBoundsException(sprintf('Range end cannot be bigger than start, %d %d given accordingly', $this->start, $this->end));
        }

        $this->start = $start;
        $this->end = $end;
    }

    /**
     * @param int $value
     *
     * @return bool
     */
    public function contains($value)
    {
        return $value >= $this->start && $value < $this->end;
    }
}
