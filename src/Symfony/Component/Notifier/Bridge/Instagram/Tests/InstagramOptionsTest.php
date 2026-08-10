<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Instagram\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Instagram\InstagramOptions;
use Symfony\Component\Notifier\Exception\InvalidArgumentException;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class InstagramOptionsTest extends TestCase
{
    public function testGetRecipientIdIsAlwaysNull()
    {
        self::assertNull((new InstagramOptions())->getRecipientId());
        self::assertNull((new InstagramOptions())->imageUrl('https://example.com/a.jpg')->getRecipientId());
    }

    public function testImageUrlSetsMediaType()
    {
        $options = (new InstagramOptions())->imageUrl('https://example.com/a.jpg');

        self::assertSame(InstagramOptions::MEDIA_TYPE_IMAGE, $options->getMediaType());
        self::assertSame('https://example.com/a.jpg', $options->getImageUrl());
        // Meta infers an image container from image_url, so media_type is not sent
        self::assertSame(['image_url' => 'https://example.com/a.jpg'], $options->toArray());
    }

    public function testVideoUrlSetsReelsMediaType()
    {
        $options = (new InstagramOptions())->videoUrl('https://example.com/a.mp4')->shareToFeed(true);

        self::assertSame(InstagramOptions::MEDIA_TYPE_REELS, $options->getMediaType());
        self::assertSame('https://example.com/a.mp4', $options->getVideoUrl());
        self::assertTrue($options->getShareToFeed());
        self::assertSame([
            'media_type' => 'REELS',
            'video_url' => 'https://example.com/a.mp4',
            'share_to_feed' => 'true',
        ], $options->toArray());
    }

    public function testMediaTypeNormalizesReelAlias()
    {
        $options = (new InstagramOptions())->mediaType('REEL');

        self::assertSame(InstagramOptions::MEDIA_TYPE_REELS, $options->getMediaType());
    }

    public function testDefaultToArray()
    {
        self::assertSame([], (new InstagramOptions())->toArray());
    }

    public function testShareToFeedIsSentForAnImagePost()
    {
        $options = (new InstagramOptions())->imageUrl('https://example.com/a.jpg')->shareToFeed(true);

        self::assertSame([
            'image_url' => 'https://example.com/a.jpg',
            'share_to_feed' => 'true',
        ], $options->toArray());
    }

    public function testUnsupportedMediaTypeIsRejected()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported Instagram media type "STORIES". Supported ones are "IMAGE", "REELS".');

        (new InstagramOptions())->mediaType('STORIES');
    }
}
