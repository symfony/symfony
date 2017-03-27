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

use Symfony\Component\Image\Filter\FilterInterface;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\BoxInterface;

/**
 * A resize filter.
 */
class Resize implements FilterInterface
{
    /**
     * @var BoxInterface
     */
    private $size;
    private $filter;

    /**
     * Constructs Resize filter with given width and height.
     *
     * @param BoxInterface $size
     * @param string       $filter
     */
    public function __construct(BoxInterface $size, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        $this->size = $size;
        $this->filter = $filter;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return $image->resize($this->size, $this->filter);
    }
}
