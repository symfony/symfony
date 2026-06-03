<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Tests\Block;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackActionsBlock;

final class SlackActionsBlockTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $actions = new SlackActionsBlock();
        $actions->button('first button text', 'https://example.org', null, 'test-value')
            ->button('second button text', 'https://example.org/slack', 'danger')
            ->button('third button text', null, null, 'test-value-3')
            ->button(
                'fourth button text',
                null,
                null,
                'test-value-4',
                [
                    'title' => [
                        'type' => 'plain_text',
                        'text' => 'test-confirm-title-4',
                    ],
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'test-confirm-text-4',
                    ],
                    'confirm' => [
                        'type' => 'plain_text',
                        'text' => 'test-confirm-confirm-4',
                    ],
                    'deny' => [
                        'type' => 'plain_text',
                        'text' => 'test-confirm-deny-4',
                    ],
                    'style' => 'danger',
                ]
            )
        ;

        $this->assertSame([
            'type' => 'actions',
            'elements' => [
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'first button text',
                    ],
                    'url' => 'https://example.org',
                    'value' => 'test-value',
                ],
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'second button text',
                    ],
                    'url' => 'https://example.org/slack',
                    'style' => 'danger',
                ],
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'third button text',
                    ],
                    'value' => 'test-value-3',
                ],
                [
                    'type' => 'button',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => 'fourth button text',
                    ],
                    'value' => 'test-value-4',
                    'confirm' => [
                        'title' => [
                            'type' => 'plain_text',
                            'text' => 'test-confirm-title-4',
                        ],
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'test-confirm-text-4',
                        ],
                        'confirm' => [
                            'type' => 'plain_text',
                            'text' => 'test-confirm-confirm-4',
                        ],
                        'deny' => [
                            'type' => 'plain_text',
                            'text' => 'test-confirm-deny-4',
                        ],
                        'style' => 'danger',
                    ],
                ],
            ],
        ], $actions->toArray());
    }

    public function testThrowsWhenFieldsLimitReached()
    {
        $section = new SlackActionsBlock();
        for ($i = 0; $i < 25; ++$i) {
            $section->button($i, $i);
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Maximum number of buttons should not exceed 25.');

        $section->button('fail', 'fail');
    }
}
