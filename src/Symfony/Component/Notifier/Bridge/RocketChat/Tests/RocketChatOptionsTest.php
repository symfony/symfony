<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatOptions;

final class RocketChatOptionsTest extends TestCase
{
    public function testRocketChatOptions()
    {
        $options = new RocketChatOptions([
            'title' => 'Test Title',
            'text' => 'Test Text',
            'color' => '#FF0000',
        ]);

        $this->assertSame([
            'attachments' => [[
                'title' => 'Test Title',
                'text' => 'Test Text',
                'color' => '#FF0000',
            ]],
        ], $options->toArray());

        $this->assertNull($options->getRecipientId());
    }

    public function testRocketChatOptionsWithChannel()
    {
        $options = (new RocketChatOptions())
            ->channel('#general');

        $this->assertSame([], $options->toArray());

        $this->assertSame('#general', $options->getRecipientId());
    }

    public function testRocketChatOptionsWithPersonalMessage()
    {
        $options = (new RocketChatOptions())
            ->channel('@username');

        $this->assertSame('@username', $options->getRecipientId());
    }

    public function testRocketChatOptionsWithComplexAttachments()
    {
        $attachments = [
            'title' => 'Rocket.Chat',
            'title_link' => 'https://rocket.chat',
            'text' => 'Rocket.Chat, the best open source chat',
            'image_url' => 'https://rocket.chat/images/mockup.png',
            'color' => '#764FA5',
            'fields' => [
                [
                    'title' => 'Priority',
                    'value' => 'High',
                    'short' => false,
                ],
                [
                    'title' => 'Type',
                    'value' => 'Post',
                    'short' => true,
                ],
            ],
        ];

        $options = new RocketChatOptions($attachments);

        $this->assertSame([
            'attachments' => [$attachments],
        ], $options->toArray());
    }

    public function testRocketChatOptionsChaining()
    {
        $options = new RocketChatOptions();

        $result = $options->channel('#tech');

        $this->assertSame($options, $result);
        $this->assertSame('#tech', $options->getRecipientId());
    }

    public function testEmptyAttachments()
    {
        $options = new RocketChatOptions();

        $this->assertSame([], $options->toArray());
    }
}
