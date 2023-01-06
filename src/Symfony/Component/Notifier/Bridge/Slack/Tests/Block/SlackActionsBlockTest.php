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
        $actions->button('first button text', 'https://example.org')
            ->button('second button text', 'https://example.org/slack', 'danger')
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
