<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\MicrosoftTeams\AdaptiveCard;

/**
 * @see https://adaptivecards.io/explorer/ImageSet.html
 */
final class ImageSet extends Element
{
    public function __construct()
    {
        $this->options = [
            'type' => 'ImageSet',
            'images' => [],
        ];
    }

    /**
     * @return $this
     */
    public function image(Image $image): static
    {
        $this->options['images'][] = $image->toArray();

        return $this;
    }

    /**
     * @return $this
     */
    public function imageSize(ImageSize $imageSize): static
    {
        $this->options['imageSize'] = $imageSize->value;

        return $this;
    }
}
