<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Threads;

use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Optional Threads post options (text / image / video).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ThreadsOptions implements MessageOptionsInterface
{
    public const MEDIA_TYPE_TEXT = 'TEXT';

    public const MEDIA_TYPE_IMAGE = 'IMAGE';

    public const MEDIA_TYPE_VIDEO = 'VIDEO';

    private string $mediaType = self::MEDIA_TYPE_TEXT;

    private ?string $imageUrl = null;

    private ?string $videoUrl = null;

    public function getRecipientId(): ?string
    {
        return null;
    }

    public function mediaType(string $mediaType): static
    {
        $this->mediaType = strtoupper($mediaType);

        return $this;
    }

    public function getMediaType(): string
    {
        return $this->mediaType;
    }

    public function imageUrl(string $url): static
    {
        $this->imageUrl = $url;
        $this->mediaType = self::MEDIA_TYPE_IMAGE;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function videoUrl(string $url): static
    {
        $this->videoUrl = $url;
        $this->mediaType = self::MEDIA_TYPE_VIDEO;

        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function toArray(): array
    {
        $options = ['media_type' => $this->mediaType];

        if (self::MEDIA_TYPE_IMAGE === $this->mediaType) {
            $options['image_url'] = $this->imageUrl;
        }

        if (self::MEDIA_TYPE_VIDEO === $this->mediaType) {
            $options['video_url'] = $this->videoUrl;
        }

        return array_filter($options, static fn ($value) => null !== $value && '' !== $value);
    }
}
