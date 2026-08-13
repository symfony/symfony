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
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackPlainTextInputBlock;

final class SlackPlainTextInputBlockTest extends TestCase
{
    public function testCanBeInstantiatedWithPlaceholder()
    {
        $block = new SlackPlainTextInputBlock('Time spent', 'time_spent_input', 'Enter some text here');
        $this->assertSame([
            'type' => 'input',
            'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'time_spent_input',
                'placeholder' => [
                    'type' => 'plain_text',
                    'text' => 'Enter some text here',
                ],
            ],
            'label' => [
                'type' => 'plain_text',
                'text' => 'Time spent',
            ],
        ], $block->toArray());
    }

    public function testCanBeInstantiatedWithoutPlaceholder()
    {
        $block = new SlackPlainTextInputBlock('Time spent', 'time_spent_input');
        $this->assertSame([
            'type' => 'input',
            'element' => [
                'type' => 'plain_text_input',
                'action_id' => 'time_spent_input',
            ],
            'label' => [
                'type' => 'plain_text',
                'text' => 'Time spent',
            ],
        ], $block->toArray());
    }

    public function testThrowsWhenLabelExceedsCharacterLimit()
    {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('Maximum length for the label text is 150 characters.');
        new SlackPlainTextInputBlock(str_repeat('a', 151), 'time_spent_input');
    }

    public function testThrowsWhenActionIdExceedsCharacterLimit()
    {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('Maximum length for the action ID is 255 characters.');
        new SlackPlainTextInputBlock('Time spent', str_repeat('a', 256));
    }

    public function testThrowsWhenPlaceholderExceedsCharacterLimit()
    {
        $this->expectException(\LengthException::class);
        $this->expectExceptionMessage('Maximum length for the placeholder text is 150 characters.');
        new SlackPlainTextInputBlock('Time spent', 'time_spent_input', str_repeat('a', 151));
    }
}
