<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatOptions;

final class GoogleChatOptionsTest extends TestCase
{
    /**
     * @group legacy
     */
    public function testToArray()
    {
        $options = new GoogleChatOptions();

        $options
            ->text('Pizza Bot')
            ->card(['header' => ['Pizza Bot Customer Support']]);

        $expected = [
            'text' => 'Pizza Bot',
            'cards' => [
                ['header' => ['Pizza Bot Customer Support']],
            ],
        ];

        $this->assertSame($expected, $options->toArray());
    }

    public function testToArrayWithCardV2()
    {
        $options = new GoogleChatOptions();

        $cardV2 = [
            'header' => [
                'title' => 'Sasha',
                'subtitle' => 'Software Engineer',
                'imageUrl' => 'https://developers.google.com/chat/images/quickstart-app-avatar.png',
                'imageType' => 'CIRCLE',
                'imageAltText' => 'Avatar for Sasha',
            ],
            'sections' => [
                [
                    'header' => 'Contact Info',
                    'collapsible' => true,
                    'widgets' => [
                        'decoratedText' => [
                            'startIcon' => ['knownIcon' => 'EMAIL'],
                            'text' => 'sasha@example.com',
                        ],
                    ],
                ],
            ],
        ];

        $options
            ->text('Hello Bot')
            ->cardV2($cardV2)
        ;

        $expected = [
            'text' => 'Hello Bot',
            'cardsV2' => [
                $cardV2,
            ],
        ];

        $this->assertSame($expected, $options->toArray());
    }

    public function testOptionsWithThread()
    {
        $thread = 'fgh.ijk';
        $options = new GoogleChatOptions();
        $options->setThreadKey($thread);
        $this->assertSame($thread, $options->getThreadKey());
        $options->setThreadKey(null);
        $this->assertNull($options->getThreadKey());
    }
}
