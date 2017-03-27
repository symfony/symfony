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
use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Filter\FilterInterface;

/**
 * A filter to render web-optimized images.
 */
class WebOptimization implements FilterInterface
{
    private $palette;
    private $path;
    private $options;

    public function __construct($path = null, array $options = array())
    {
        $this->path = $path;
        $this->options = array_replace(array(
            'resolution_units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
            'resolution_y' => 72,
            'resolution_x' => 72,
        ), $options);

        foreach (array('resolution-x', 'resolution-y', 'resolution-units') as $option) {
            if (isset($this->options[$option])) {
                @trigger_error(sprintf('"%s" as been deprecated in Symfony 3.3 in favor of "%"', $option, str_replace('-', '_', $option)), E_USER_DEPRECATED);
                $this->options[str_replace('-', '_', $option)] = $this->options[$option];
                unset($this->options[$option]);
            }
        }

        $this->palette = new RGB();
    }

    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
        $image
            ->usePalette($this->palette)
            ->strip();

        if (is_callable($this->path)) {
            $path = call_user_func($this->path, $image);
        } elseif (null !== $this->path) {
            $path = $this->path;
        } else {
            return $image;
        }

        return $image->save($path, $this->options);
    }
}
