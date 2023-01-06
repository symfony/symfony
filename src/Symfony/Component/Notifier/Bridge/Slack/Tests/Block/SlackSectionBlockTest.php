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
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackImageBlockElement;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;

final class SlackSectionBlockTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $section = new SlackSectionBlock();
        $section->text('section text');
        $section->field('section field');
        $section->accessory(new SlackImageBlockElement('https://example.com/image.jpg', 'an image'));

        $this->assertSame([
            'type' => 'section',
            'text' => [
                'type' => 'mrkdwn',
                'text' => 'section text',
            ],
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => 'section field',
                ],
            ],
            'accessory' => [
                'type' => 'image',
                'image_url' => 'https://example.com/image.jpg',
                'alt_text' => 'an image',
            ],
        ], $section->toArray());
    }

    public function testThrowsWhenFieldsLimitReached()
    {
        $section = new SlackSectionBlock();
        for ($i = 0; $i < 10; ++$i) {
            $section->field($i);
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Maximum number of fields should not exceed 10.');

        $section->field('fail');
    }
}
