<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Gmagick;

use Symfony\Component\Image\Image\AbstractLayers;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Exception\NotSupportedException;
use Symfony\Component\Image\Exception\OutOfBoundsException;
use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Image\Palette\PaletteInterface;

class Layers extends AbstractLayers
{
    /**
     * @var Image
     */
    private $image;

    /**
     * @var \Gmagick
     */
    private $resource;

    /**
     * @var int
     */
    private $offset = 0;

    /**
     * @var array
     */
    private $layers = array();

    /**
     * @var PaletteInterface
     */
    private $palette;

    public function __construct(Image $image, PaletteInterface $palette, \Gmagick $resource)
    {
        $this->image = $image;
        $this->resource = $resource;
        $this->palette = $palette;
    }

    /**
     * {@inheritdoc}
     */
    public function merge()
    {
        foreach ($this->layers as $offset => $image) {
            try {
                $this->resource->setimageindex($offset);
                $this->resource->setimage($image->getGmagick());
            } catch (\GmagickException $e) {
                throw new RuntimeException('Failed to substitute layer', $e->getCode(), $e);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function coalesce()
    {
        throw new NotSupportedException('Gmagick does not support coalescing');
    }

    /**
     * {@inheritdoc}
     */
    public function animate($format, $delay, $loops)
    {
        if ('gif' !== strtolower($format)) {
            throw new NotSupportedException('Animated picture is currently only supported on gif');
        }

        if (!is_int($loops) || $loops < 0) {
            throw new InvalidArgumentException('Loops must be a positive integer.');
        }

        if (null !== $delay && (!is_int($delay) || $delay < 0)) {
            throw new InvalidArgumentException('Delay must be either null or a positive integer.');
        }

        try {
            foreach ($this as $offset => $layer) {
                $this->resource->setimageindex($offset);
                $this->resource->setimageformat($format);

                if (null !== $delay) {
                    $this->resource->setimagedelay($delay / 10);
                }

                $this->resource->setimageiterations($loops);
            }
        } catch (\GmagickException $e) {
            throw new RuntimeException('Failed to animate layers', $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return $this->extractAt($this->offset);
    }

    /**
     * Tries to extract layer at given offset.
     *
     * @param int $offset
     *
     * @return Image
     *
     * @throws RuntimeException
     */
    private function extractAt($offset)
    {
        if (!isset($this->layers[$offset])) {
            try {
                $this->resource->setimageindex($offset);
                $this->layers[$offset] = new Image($this->resource->getimage(), $this->palette, new MetadataBag());
            } catch (\GmagickException $e) {
                throw new RuntimeException(sprintf('Failed to extract layer %d', $offset), $e->getCode(), $e);
            }
        }

        return $this->layers[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        ++$this->offset;
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        $this->offset = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->offset < count($this);
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        try {
            return $this->resource->getnumberimages();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Failed to count the number of layers', $e->getCode(), $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset)
    {
        return is_int($offset) && $offset >= 0 && $offset < count($this);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->extractAt($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $image)
    {
        if (!$image instanceof Image) {
            throw new InvalidArgumentException('Only a Gmagick Image can be used as layer');
        }

        if (null === $offset) {
            $offset = count($this) - 1;
        } else {
            if (!is_int($offset)) {
                throw new InvalidArgumentException('Invalid offset for layer, it must be an integer');
            }

            if (count($this) < $offset || 0 > $offset) {
                throw new OutOfBoundsException(sprintf('Invalid offset for layer, it must be a value between 0 and %d, %d given', count($this), $offset));
            }

            if (isset($this[$offset])) {
                unset($this[$offset]);
                $offset = $offset - 1;
            }
        }

        $frame = $image->getGmagick();

        try {
            if (count($this) > 0) {
                $this->resource->setimageindex($offset);
                $this->resource->nextimage();
            }
            $this->resource->addimage($frame);

            /*
             * ugly hack to bypass issue https://bugs.php.net/bug.php?id=64623
             */
            if (count($this) == 2) {
                $this->resource->setimageindex($offset + 1);
                $this->resource->nextimage();
                $this->resource->addimage($frame);
                unset($this[0]);
            }
        } catch (\GmagickException $e) {
            throw new RuntimeException('Unable to set the layer', $e->getCode(), $e);
        }

        $this->layers = array();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset)
    {
        try {
            $this->extractAt($offset);
        } catch (RuntimeException $e) {
            return;
        }

        try {
            $this->resource->setimageindex($offset);
            $this->resource->removeimage();
        } catch (\GmagickException $e) {
            throw new RuntimeException('Unable to remove layer', $e->getCode(), $e);
        }
    }
}
