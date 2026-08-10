<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Threads\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Threads\ThreadsOptions;

/**
 * @author Mathieu Ledru <matyo91@gmail.com>
 */
final class ThreadsOptionsTest extends TestCase
{
    public function testGetRecipientIdIsAlwaysNull()
    {
        self::assertNull((new ThreadsOptions())->getRecipientId());
        self::assertNull((new ThreadsOptions())->imageUrl('https://example.com/a.jpg')->getRecipientId());
    }

    public function testImageUrlSetsMediaType()
    {
        $options = (new ThreadsOptions())->imageUrl('https://example.com/a.jpg');

        self::assertSame(ThreadsOptions::MEDIA_TYPE_IMAGE, $options->getMediaType());
        self::assertSame('https://example.com/a.jpg', $options->getImageUrl());
        self::assertSame([
            'media_type' => 'IMAGE',
            'image_url' => 'https://example.com/a.jpg',
        ], $options->toArray());
    }

    public function testVideoUrlSetsMediaType()
    {
        $options = (new ThreadsOptions())->videoUrl('https://example.com/a.mp4');

        self::assertSame(ThreadsOptions::MEDIA_TYPE_VIDEO, $options->getMediaType());
        self::assertSame('https://example.com/a.mp4', $options->getVideoUrl());
        self::assertSame([
            'media_type' => 'VIDEO',
            'video_url' => 'https://example.com/a.mp4',
        ], $options->toArray());
    }

    public function testDefaultToArray()
    {
        self::assertSame(['media_type' => 'TEXT'], (new ThreadsOptions())->toArray());
    }
}
