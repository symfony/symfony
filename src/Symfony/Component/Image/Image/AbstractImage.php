<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image;

use Symfony\Component\Image\Exception\InvalidArgumentException;
use Symfony\Component\Image\Image\Metadata\MetadataBag;

abstract class AbstractImage implements ImageInterface
{
    /**
     * @var MetadataBag
     */
    protected $metadata;

    /**
     * {@inheritdoc}
     *
     * @return ImageInterface
     */
    public function thumbnail(BoxInterface $size, $mode = ImageInterface::THUMBNAIL_INSET, $filter = ImageInterface::FILTER_UNDEFINED)
    {
        if ($mode !== ImageInterface::THUMBNAIL_INSET &&
            $mode !== ImageInterface::THUMBNAIL_OUTBOUND) {
            throw new InvalidArgumentException('Invalid mode specified');
        }

        $imageSize = $this->getSize();
        $ratios = array(
            $size->getWidth() / $imageSize->getWidth(),
            $size->getHeight() / $imageSize->getHeight()
        );

        $thumbnail = $this->copy();

        $thumbnail->usePalette($this->palette());
        $thumbnail->strip();
        // if target width is larger than image width
        // AND target height is longer than image height
        if ($size->contains($imageSize)) {
            return $thumbnail;
        }

        if ($mode === ImageInterface::THUMBNAIL_INSET) {
            $ratio = min($ratios);
        } else {
            $ratio = max($ratios);
        }

        if ($mode === ImageInterface::THUMBNAIL_OUTBOUND) {
            if (!$imageSize->contains($size)) {
                $size = new Box(
                    min($imageSize->getWidth(), $size->getWidth()),
                    min($imageSize->getHeight(), $size->getHeight())
                );
            } else {
                $imageSize = $thumbnail->getSize()->scale($ratio);
                $thumbnail->resize($imageSize, $filter);
            }
            $thumbnail->crop(new Point(
                max(0, round(($imageSize->getWidth() - $size->getWidth()) / 2)),
                max(0, round(($imageSize->getHeight() - $size->getHeight()) / 2))
            ), $size);
        } else {
            if (!$imageSize->contains($size)) {
                $imageSize = $imageSize->scale($ratio);
                $thumbnail->resize($imageSize, $filter);
            } else {
                $imageSize = $thumbnail->getSize()->scale($ratio);
                $thumbnail->resize($imageSize, $filter);
            }
        }

        return $thumbnail;
    }

    /**
     * Updates a given array of save options for backward compatibility with legacy names
     *
     * @param array $options
     *
     * @return array
     */
    protected function updateSaveOptions(array $options)
    {
        // Preserve BC until version 1.0
        if (isset($options['quality']) && !isset($options['jpeg_quality'])) {
            $options['jpeg_quality'] = $options['quality'];
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function metadata()
    {
        return $this->metadata;
    }

    /**
     * Assures the metadata instance will be cloned, too
     */
    public function __clone()
    {
        if ($this->metadata !== null) {
            $this->metadata = clone $this->metadata;
        }
    }

}
