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

/**
 * The RelativeResize filter allows images to be resized relative to their
 * existing dimensions.
 */
class RelativeResize implements FilterInterface
{
    private $method;
    private $parameter;

    /**
     * Constructs a RelativeResize filter with the given method and argument.
     *
     * @param string $method    BoxInterface method
     * @param mixed  $parameter Parameter for BoxInterface method
     */
    public function __construct($method, $parameter)
    {
        if (!in_array($method, array('heighten', 'increase', 'scale', 'widen'))) {
            throw new InvalidArgumentException(sprintf('Unsupported method: %s', $method));
        }

        $this->method = $method;
        $this->parameter = $parameter;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return $image->resize(call_user_func(array($image->getSize(), $this->method), $this->parameter));
    }
}
