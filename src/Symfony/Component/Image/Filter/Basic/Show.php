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
use Symfony\Component\Image\Filter\FilterInterface;

/**
 * A show filter.
 */
class Show implements FilterInterface
{
    /**
     * @var string
     */
    private $format;

    /**
     * @var array
     */
    private $options;

    /**
     * Constructs the Show filter with given format and options.
     *
     * @param string $format
     * @param array  $options
     */
    public function __construct($format, array $options = array())
    {
        $this->format = $format;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        return $image->show($this->format, $this->options);
    }
}
