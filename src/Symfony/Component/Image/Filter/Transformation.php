<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Filter;

use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Filter\Basic\ApplyMask;
use Symfony\Component\Image\Filter\Basic\Copy;
use Symfony\Component\Image\Filter\Basic\Crop;
use Symfony\Component\Image\Filter\Basic\Fill;
use Symfony\Component\Image\Filter\Basic\FlipVertically;
use Symfony\Component\Image\Filter\Basic\FlipHorizontally;
use Symfony\Component\Image\Filter\Basic\Paste;
use Symfony\Component\Image\Filter\Basic\Resize;
use Symfony\Component\Image\Filter\Basic\Rotate;
use Symfony\Component\Image\Filter\Basic\Save;
use Symfony\Component\Image\Filter\Basic\Show;
use Symfony\Component\Image\Filter\Basic\Strip;
use Symfony\Component\Image\Filter\Basic\Thumbnail;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\LoaderInterface;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Fill\FillInterface;
use Symfony\Component\Image\Image\ManipulatorInterface;
use Symfony\Component\Image\Image\PointInterface;

/**
 * A transformation filter
 */
final class Transformation implements FilterInterface, ManipulatorInterface
{
    /**
     * @var array
     */
    private $filters = array();

    /**
     * @var array
     */
    private $sorted;

    /**
     * An LoaderInterface instance.
     *
     * @var LoaderInterface
     */
    private $loader;

    /**
     * Class constructor.
     *
     * @param LoaderInterface $loader An LoaderInterface instance
     */
    public function __construct(LoaderInterface $loader = null)
    {
        $this->loader = $loader;
    }

    /**
     * Applies a given FilterInterface onto given ImageInterface and returns
     * modified ImageInterface
     *
     * @param ImageInterface  $image
     * @param FilterInterface $filter
     *
     * @return ImageInterface
     * @throws InvalidArgumentException
     */
    public function applyFilter(ImageInterface $image, FilterInterface $filter)
    {
        if ($filter instanceof LoaderAware) {
            if ($this->loader === null) {
                throw new InvalidArgumentException(sprintf('In order to use %s pass an Symfony\Component\Image\Image\LoaderInterface instance to Transformation constructor', get_class($filter)));
            }
            $filter->setLoader($this->loader);
        }

        return $filter->apply($image);
    }

    /**
     * Returns a list of filters sorted by their priority. Filters with same priority will be returned in the order they were added.
     *
     * @return array
     */
    public function getFilters()
    {
        if (null === $this->sorted) {
            if (!empty($this->filters)) {
                ksort($this->filters);
                $this->sorted = call_user_func_array('array_merge', $this->filters);
            } else {
                $this->sorted = array();
            }
        }

        return $this->sorted;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return array_reduce(
            $this->getFilters(),
            array($this, 'applyFilter'),
            $image
        );
    }

    /**
     * {@inheritdoc}
     */
    public function copy()
    {
        return $this->add(new Copy());
    }

    /**
     * {@inheritdoc}
     */
    public function crop(PointInterface $start, BoxInterface $size)
    {
        return $this->add(new Crop($start, $size));
    }

    /**
     * {@inheritdoc}
     */
    public function flipHorizontally()
    {
        return $this->add(new FlipHorizontally());
    }

    /**
     * {@inheritdoc}
     */
    public function flipVertically()
    {
        return $this->add(new FlipVertically());
    }

    /**
     * {@inheritdoc}
     */
    public function strip()
    {
        return $this->add(new Strip());
    }

    /**
     * {@inheritdoc}
     */
    public function paste(ImageInterface $image, PointInterface $start)
    {
        return $this->add(new Paste($image, $start));
    }

    /**
     * {@inheritdoc}
     */
    public function applyMask(ImageInterface $mask)
    {
        return $this->add(new ApplyMask($mask));
    }

    /**
     * {@inheritdoc}
     */
    public function fill(FillInterface $fill)
    {
        return $this->add(new Fill($fill));
    }

    /**
     * {@inheritdoc}
     */
    public function resize(BoxInterface $size, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        return $this->add(new Resize($size, $filter));
    }

    /**
     * {@inheritdoc}
     */
    public function rotate($angle, ColorInterface $background = null)
    {
        return $this->add(new Rotate($angle, $background));
    }

    /**
     * {@inheritdoc}
     */
    public function save($path = null, array $options = array())
    {
        return $this->add(new Save($path, $options));
    }

    /**
     * {@inheritdoc}
     */
    public function show($format, array $options = array())
    {
        return $this->add(new Show($format, $options));
    }

    /**
     * {@inheritdoc}
     */
    public function thumbnail(BoxInterface $size, $mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        return $this->add(new Thumbnail($size, $mode, $filter));
    }

    /**
     * Registers a given FilterInterface in an internal array of filters for
     * later application to an instance of ImageInterface
     *
     * @param  FilterInterface $filter
     * @param  int             $priority
     * @return Transformation
     */
    public function add(FilterInterface $filter, $priority = 0)
    {
        $this->filters[$priority][] = $filter;
        $this->sorted = null;

        return $this;
    }
}
