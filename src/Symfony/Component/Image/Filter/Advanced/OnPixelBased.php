<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Filter\Advanced;

use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Filter\FilterInterface;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Point;

/**
 * The OnPixelBased takes a callable, and for each pixel, this callable is called with the
 * image  (Symfony\Component\Image\Image\ImageInterface) and the current point (Symfony\Component\Image\Image\Point).
 */
class OnPixelBased implements FilterInterface
{
    protected $callback;

    public function __construct($callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('$callback has to be callable');
        }

        $this->callback = $callback;
    }

    /**
     * Applies scheduled transformation to ImageInterface instance
     * Returns processed ImageInterface instance.
     *
     * @param ImageInterface $image
     *
     * @return ImageInterface
     */
    public function apply(ImageInterface $image)
    {
        $w = $image->getSize()->getWidth();
        $h = $image->getSize()->getHeight();

        for ($x = 0; $x < $w; ++$x) {
            for ($y = 0; $y < $h; ++$y) {
                call_user_func($this->callback, $image, new Point($x, $y));
            }
        }

        return $image;
    }
}
