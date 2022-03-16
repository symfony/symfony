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
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackContextBlock;

final class SlackContextBlockTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $context = new SlackContextBlock();
        $context->text('context text without emoji', false, false);
        $context->text('context text verbatim', true, false, true);
        $context->image('https://example.com/image.jpg', 'an image');
        $context->id('context_id');

        $this->assertSame([
            'type' => 'context',
            'elements' => [
                [
                    'type' => 'plain_text',
                    'text' => 'context text without emoji',
                    'emoji' => false,
                ],
                [
                    'type' => 'mrkdwn',
                    'text' => 'context text verbatim',
                    'verbatim' => true,
                ],
                [
                    'type' => 'image',
                    'image_url' => 'https://example.com/image.jpg',
                    'alt_text' => 'an image',
                ],
            ],
            'block_id' => 'context_id',
        ], $context->toArray());
    }

    public function testThrowsWhenElementsLimitReached()
    {
        $context = new SlackContextBlock();
        for ($i = 0; $i < 10; ++$i) {
            if (0 === $i % 2) {
                $context->text($i);
            } else {
                $context->image($i, $i);
            }
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Maximum number of elements should not exceed 10.');

        $context->text('fail');
    }
}
