<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Message\MessageOptionsInterface;

/**
 * Instagram content-publishing options (image feed post or Reel).
 *
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class InstagramOptions implements MessageOptionsInterface
{
    public const MEDIA_TYPE_IMAGE = 'IMAGE';

    public const MEDIA_TYPE_REELS = 'REELS';

    private string $mediaType = self::MEDIA_TYPE_IMAGE;

    private ?string $imageUrl = null;

    private ?string $videoUrl = null;

    private ?bool $shareToFeed = null;

    public function getRecipientId(): ?string
    {
        return null;
    }

    public function mediaType(string $mediaType): static
    {
        $normalized = strtoupper($mediaType);
        $normalized = 'REEL' === $normalized ? self::MEDIA_TYPE_REELS : $normalized;

        if (!\in_array($normalized, [self::MEDIA_TYPE_IMAGE, self::MEDIA_TYPE_REELS], true)) {
            throw new InvalidArgumentException(\sprintf('Unsupported Instagram media type "%s". Supported ones are "%s".', $mediaType, implode('", "', [self::MEDIA_TYPE_IMAGE, self::MEDIA_TYPE_REELS])));
        }

        $this->mediaType = $normalized;

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
        $this->mediaType = self::MEDIA_TYPE_REELS;

        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->videoUrl;
    }

    public function shareToFeed(bool $shareToFeed): static
    {
        $this->shareToFeed = $shareToFeed;

        return $this;
    }

    public function getShareToFeed(): ?bool
    {
        return $this->shareToFeed;
    }

    public function toArray(): array
    {
        if (self::MEDIA_TYPE_REELS === $this->mediaType) {
            $options = [
                'media_type' => self::MEDIA_TYPE_REELS,
                'video_url' => $this->videoUrl,
            ];
        } else {
            // Meta infers an image container from image_url alone, media_type is not sent
            $options = ['image_url' => $this->imageUrl];
        }

        if (null !== $this->shareToFeed) {
            $options['share_to_feed'] = $this->shareToFeed ? 'true' : 'false';
        }

        return array_filter($options, static fn ($value) => null !== $value && '' !== $value);
    }
}
