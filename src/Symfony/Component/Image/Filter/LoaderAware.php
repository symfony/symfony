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
use Symfony\Component\Image\Image\LoaderInterface;

/**
 * LoaderAware base class
 */
abstract class LoaderAware implements FilterInterface
{
    /**
     * An LoaderInterface instance.
     *
     * @var LoaderInterface
     */
    private $loader;

    /**
     * Set LoaderInterface instance.
     *
     * @param LoaderInterface $loader An LoaderInterface instance
     */
    public function setLoader(LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Get LoaderInterface instance.
     *
     * @return LoaderInterface
     *
     * @throws InvalidArgumentException
     */
    public function getLoader()
    {
        if (!$this->loader instanceof LoaderInterface) {
            throw new InvalidArgumentException(sprintf('In order to use %s pass an Symfony\Component\Image\Image\LoaderInterface instance to filter constructor', get_class($this)));
        }

        return $this->loader;
    }
}
