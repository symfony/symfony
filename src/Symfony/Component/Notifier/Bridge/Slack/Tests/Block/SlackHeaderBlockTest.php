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
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackHeaderBlock;
use Symfony\Component\Notifier\Exception\LengthException;

final class SlackHeaderBlockTest extends TestCase
{
    public function testCanBeInstantiated()
    {
        $header = new SlackHeaderBlock('header text');
        $header->id('header_id');

        $this->assertSame([
            'type' => 'header',
            'text' => [
                'type' => 'plain_text',
                'text' => 'header text',
            ],
            'block_id' => 'header_id',
        ], $header->toArray());
    }

    public function testThrowsWhenTextExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the text is 150 characters.');

        new SlackHeaderBlock(str_repeat('h', 151));
    }

    public function testThrowsWhenBlockIdExceedsCharacterLimit()
    {
        $this->expectException(LengthException::class);
        $this->expectExceptionMessage('Maximum length for the block id is 255 characters.');

        $header = new SlackHeaderBlock('header');
        $header->id(str_repeat('h', 256));
    }
}
