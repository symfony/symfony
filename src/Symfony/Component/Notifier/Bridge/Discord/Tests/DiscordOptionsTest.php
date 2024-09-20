<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Discord\DiscordOptions;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordAuthorEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordEmbed;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordFooterEmbedObject;
use Symfony\Component\Notifier\Bridge\Discord\Embeds\DiscordMediaEmbedObject;

final class DiscordOptionsTest extends TestCase
{
    public function testDiscordOptions()
    {
        $discordOptions = (new DiscordOptions())
            ->username('name of the bot')
            ->avatarUrl('http://ava.tar/pic.png')
            ->tts(true);

        $this->assertSame($discordOptions->toArray(), [
            'username' => 'name of the bot',
            'avatar_url' => 'http://ava.tar/pic.png',
            'tts' => true,
        ]);
    }

    public function testDiscordEmbedFields()
    {
        $discordOptions = (new DiscordOptions())
            ->addEmbed((new DiscordEmbed())
                ->description('descript.io')
                ->url('http://ava.tar/pic.png')
                ->timestamp(new \DateTimeImmutable('2020-10-12 9:14:15+0000'))
                ->color(2021216)
                ->title('New song added!')
            )
            ->addEmbed((new DiscordEmbed())
                ->description('descript.io 2')
                ->url('http://ava.tar/pic.png')
                ->timestamp(new \DateTimeImmutable('2020-10-12 9:14:15+0000'))
                ->color(2021216)
                ->title('New song added!')
            );

        $this->assertSame($discordOptions->toArray(), [
            'embeds' => [
                [
                    'description' => 'descript.io',
                    'url' => 'http://ava.tar/pic.png',
                    'timestamp' => '2020-10-12T09:14:15+0000',
                    'color' => 2021216,
                    'title' => 'New song added!',
                ],
                [
                    'description' => 'descript.io 2',
                    'url' => 'http://ava.tar/pic.png',
                    'timestamp' => '2020-10-12T09:14:15+0000',
                    'color' => 2021216,
                    'title' => 'New song added!',
                ],
            ],
        ]);

        $discordOptions = (new DiscordOptions())
            ->addEmbed((new DiscordEmbed())
                ->description('descript.io')
                ->url('http://ava.tar/pic.png')
                ->timestamp(new \DateTimeImmutable('2020-10-12 9:14:15+0000'))
                ->color(2021216)
                ->title('New song added!')
                ->footer(
                    (new DiscordFooterEmbedObject())
                        ->text('text')
                        ->iconUrl('icon url')
                        ->proxyIconUrl('proxy icon url')
                )
                ->thumbnail(
                    (new DiscordMediaEmbedObject())
                        ->url('https://thumb.ur.l/')
                        ->proxyUrl('https://proxy.ur.l/')
                        ->height(900)
                        ->width(600)
                )
                ->image(
                    (new DiscordMediaEmbedObject())
                        ->url('https://image.ur.l/')
                        ->proxyUrl('https://proxy.ur.l/')
                        ->height(900)
                        ->width(600)
                )
                ->author(
                    (new DiscordAuthorEmbedObject())
                        ->name('name field')
                        ->url('https://ur.l/')
                        ->iconUrl('https://icon.ur.l/')
                        ->proxyIconUrl('https://proxy.ic.on/url')
                )
            );

        $this->assertSame($discordOptions->toArray(), [
            'embeds' => [
                [
                    'description' => 'descript.io',
                    'url' => 'http://ava.tar/pic.png',
                    'timestamp' => '2020-10-12T09:14:15+0000',
                    'color' => 2021216,
                    'title' => 'New song added!',
                    'footer' => [
                        'text' => 'text',
                        'icon_url' => 'icon url',
                        'proxy_icon_url' => 'proxy icon url',
                    ],
                    'thumbnail' => [
                        'url' => 'https://thumb.ur.l/',
                        'proxy_url' => 'https://proxy.ur.l/',
                        'height' => 900,
                        'width' => 600,
                    ],
                    'image' => [
                        'url' => 'https://image.ur.l/',
                        'proxy_url' => 'https://proxy.ur.l/',
                        'height' => 900,
                        'width' => 600,
                    ],
                    'author' => [
                        'name' => 'name field',
                        'url' => 'https://ur.l/',
                        'icon_url' => 'https://icon.ur.l/',
                        'proxy_icon_url' => 'https://proxy.ic.on/url',
                    ],
                ],
            ],
        ]);
    }

    public function testDiscordFooterEmbedFields()
    {
        $footer = (new DiscordFooterEmbedObject())
            ->text('text')
            ->iconUrl('icon url')
            ->proxyIconUrl('proxy icon url')
        ;

        $this->assertSame($footer->toArray(), [
            'text' => 'text',
            'icon_url' => 'icon url',
            'proxy_icon_url' => 'proxy icon url',
        ]);
    }

    public function testDiscordMediaEmbedFields()
    {
        $media = (new DiscordMediaEmbedObject())
            ->url('https://ur.l/')
            ->proxyUrl('https://proxy.ur.l/')
            ->height(900)
            ->width(600)
        ;

        $this->assertSame($media->toArray(), [
            'url' => 'https://ur.l/',
            'proxy_url' => 'https://proxy.ur.l/',
            'height' => 900,
            'width' => 600,
        ]);
    }

    public function testDiscordAuthorEmbedFields()
    {
        $author = (new DiscordAuthorEmbedObject())
            ->name('name field')
            ->url('https://ur.l/')
            ->iconUrl('https://icon.ur.l/')
            ->proxyIconUrl('https://proxy.ic.on/url')
        ;

        $this->assertSame($author->toArray(), [
            'name' => 'name field',
            'url' => 'https://ur.l/',
            'icon_url' => 'https://icon.ur.l/',
            'proxy_icon_url' => 'https://proxy.ic.on/url',
        ]);
    }
}
