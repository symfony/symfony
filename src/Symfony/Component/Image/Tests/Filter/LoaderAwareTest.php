<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Filter;

use Symfony\Component\Image\Filter\Transformation;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\LoaderInterface;

/**
 * LoaderAwareTest.
 */
class LoaderAwareTest extends FilterTestCase
{
    /**
     * Test if filter works when passing Loader instance directly.
     */
    public function testFilterWorksWhenPassedLoaderAndCalledDirectly()
    {
        $loaderMock = $this->getLoaderMock();

        $filter = new DummyLoaderAwareFilter();
        $filter->setLoader($loaderMock);
        $image = $filter->apply($this->getImage());

        $this->assertInstanceOf(ImageInterface::class, $image);
    }

    /**
     * Test if filter works when passing Loader instance via
     * Transformation.
     */
    public function testFilterWorksWhenPassedLoaderViaTransformation()
    {
        $loaderMock = $this->getLoaderMock();

        $filters = new Transformation($loaderMock);
        $filters->add(new DummyLoaderAwareFilter());
        $image = $filters->apply($this->getImage());

        $this->assertInstanceOf(ImageInterface::class, $image);
    }

    /**
     * Test if filter throws exception when called directly without
     * passing Loader instance.
     *
     * @expectedException \Symfony\Component\Image\Exception\InvalidArgumentException
     */
    public function testFilterThrowsExceptionWhenCalledDirectly()
    {
        $filter = new DummyLoaderAwareFilter();
        $filter->apply($this->getImage());
    }

    /**
     * Test if filter throws exception via Transformation without
     * passing Loader instance.
     *
     * @expectedException \Symfony\Component\Image\Exception\InvalidArgumentException
     */
    public function testFilterThrowsExceptionViaTransformation()
    {
        $filters = new Transformation();
        $filters->add(new DummyLoaderAwareFilter());
        $filters->apply($this->getImage());
    }

    protected function getLoaderMock()
    {
        $loaderMock = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $loaderMock->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->getImage()));

        return $loaderMock;
    }
}
