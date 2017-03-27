<?php

namespace Symfony\Component\Image\Tests\Filter;

use Symfony\Component\Image\Filter\LoaderAware;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\ImageInterface;

/**
 * DummyLoaderAwareFilter.
 */
class DummyLoaderAwareFilter extends LoaderAware
{
    /**
     * Apply filter.
     *
     * @param  ImageInterface $image An ImageInterface instance
     * @return ImageInterface
     */
    public function apply(ImageInterface $image)
    {
        return $this->getLoader()->create(new Box(200, 200));
    }
}
