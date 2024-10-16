<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\LineBot\LineBotOptions;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Notification\Notification;

final class LineBotOptionsTest extends TestCase
{
    public function notificationProvider()
    {
        yield [
            'notification' => new Notification('Hello'),
            'expected' => [
                [
                    'type' => 'text',
                    'text' => 'Hello',
                ],
            ],
        ];

        yield [
            'notification' => (new Notification('Hello'))->emoji('👋'),
            'expected' => [
                [
                    'type' => 'text',
                    'text' => '👋 Hello',
                ],
            ],
        ];

        yield [
            'notification' => (new Notification('Hello'))->content('World'),
            'expected' => [
                [
                    'type' => 'text',
                    'text' => "Hello\nWorld",
                ],
            ],
        ];

        yield [
            'notification' => (new Notification('Hello'))->emoji('👋')->content('World'),
            'expected' => [
                [
                    'type' => 'text',
                    'text' => "👋 Hello\nWorld",
                ],
            ],
        ];
    }

    public function toArrayProvider()
    {
        yield [
            'options' => (new LineBotOptions())
                ->to('test')
                ->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ]),
            'expected' => [
                'to' => 'test',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello',
                    ],
                ],
                'notificationDisabled' => null,
                'customAggregationUnits' => null,
            ],
        ];

        yield [
            'options' => (new LineBotOptions())
                ->to('test')
                ->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ])
                ->disableNotification(true),
            'expected' => [
                'to' => 'test',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello',
                    ],
                ],
                'notificationDisabled' => true,
                'customAggregationUnits' => null,
            ],
        ];

        yield [
            'options' => (new LineBotOptions())
                ->to('test')
                ->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ])
                ->customAggregationUnits(['unit']),
            'expected' => [
                'to' => 'test',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello',
                    ],
                ],
                'notificationDisabled' => null,
                'customAggregationUnits' => ['unit'],
            ],
        ];

        yield [
            'options' => (new LineBotOptions())
                ->to('test')
                ->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ])
                ->customAggregationUnits(['unit'])
                ->disableNotification(true),
            'expected' => [
                'to' => 'test',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello',
                    ],
                ],
                'notificationDisabled' => true,
                'customAggregationUnits' => ['unit'],
            ],
        ];

        yield [
            'options' => (new LineBotOptions())
                ->to('test')
                ->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ])
                ->addMessage([
                    'type' => 'text',
                    'text' => 'World',
                ]),
            'expected' => [
                'to' => 'test',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'World',
                    ],
                ],
                'notificationDisabled' => null,
                'customAggregationUnits' => null,
            ],
        ];

        yield [
            'options' => (new LineBotOptions())
                ->to('test')
                ->addMessage([
                    'type' => 'text',
                    'text' => 'Hello',
                ])
                ->addMessage([
                    'type' => 'text',
                    'text' => 'World',
                ])
                ->disableNotification(false),
            'expected' => [
                'to' => 'test',
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => 'Hello',
                    ],
                    [
                        'type' => 'text',
                        'text' => 'World',
                    ],
                ],
                'notificationDisabled' => false,
                'customAggregationUnits' => null,
            ],
        ];
    }

    /**
     * @dataProvider notificationProvider
     */
    public function testNotification(Notification $notification, array $expected)
    {
        $options = LineBotOptions::fromNotification($notification);
        $this->assertSame($expected, $options->toArray()['messages']);
    }

    /**
     * @dataProvider toArrayProvider
     */
    public function testToArray(LineBotOptions $options, array $expected)
    {
        $this->assertSame($expected, $options->toArray());
    }

    public function testOverFiveMessages()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You can only add up to 5 messages');

        (new LineBotOptions())
            ->to('test')
            ->addMessage([
                'type' => 'text',
                'text' => 'Hello',
            ])
            ->addMessage([
                'type' => 'text',
                'text' => 'World',
            ])
            ->addMessage([
                'type' => 'text',
                'text' => 'Hello',
            ])
            ->addMessage([
                'type' => 'text',
                'text' => 'World',
            ])
            ->addMessage([
                'type' => 'text',
                'text' => 'Hello',
            ])
            ->addMessage([
                'type' => 'text',
                'text' => 'World',
            ]);
    }

    public function testEmptyCustomAggregationUnits()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('You must provide at least one aggregation unit if units is not null.');

        (new LineBotOptions())
            ->to('test')
            ->customAggregationUnits([]);
    }
}
