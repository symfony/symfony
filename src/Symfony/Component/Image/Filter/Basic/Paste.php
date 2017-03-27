<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Filter\Basic;

use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Filter\FilterInterface;

/**
 * A paste filter.
 */
class Paste implements FilterInterface
{
    /**
     * @var ImageInterface
     */
    private $image;

    /**
     * @var PointInterface
     */
    private $start;

    /**
     * Constructs a Paste filter with given ImageInterface to paste and x, y
     * coordinates of target position.
     *
     * @param ImageInterface $image
     * @param PointInterface $start
     */
    public function __construct(ImageInterface $image, PointInterface $start)
    {
        $this->image = $image;
        $this->start = $start;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return $image->paste($this->image, $this->start);
    }
}
