<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Bridge\Slack\Block\SlackSectionBlock;

final class SlackSectionBlockTest extends TestCase
{
    /**
     * @dataProvider provideTextData
     */
    public function testSetText(array $expectedBlockArray, string $text, bool $markdown): void
    {
        $block = new SlackSectionBlock();
        $block->text($text, $markdown);

        $this->assertSame($expectedBlockArray, $block->toArray());
    }

    public function provideTextData(): iterable
    {
        yield [['type' => 'section', 'fields' => [], 'text' => ['type' => 'mrkdwn', 'text' => 'FooText']], 'FooText', true];
        yield [['type' => 'section', 'fields' => [], 'text' => ['type' => 'plain_text', 'text' => 'FooText']], 'FooText', false];
    }

    public function testSetTextTwice(): void
    {
        $block = new SlackSectionBlock();
        $block->text('FooText')
            ->text('BarText', false)
        ;

        $expectedBlockArray = [
            'type' => 'section',
            'fields' => [],
            'text' => [
                'type' => 'plain_text',
                'text' => 'BarText',
            ],
        ];

        $this->assertSame($expectedBlockArray, $block->toArray());
    }

    public function testAddField(): void
    {
        $block = new SlackSectionBlock();

        $block->field('FooText');

        $expectedBlockArray = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => 'FooText',
                ],
            ],
        ];

        $this->assertSame($expectedBlockArray, $block->toArray());
    }

    public function testAddMultipleFields(): void
    {
        $block = new SlackSectionBlock();

        $block->field('FooText')
            ->field('BarText', false)
        ;

        $expectedBlockArray = [
            'type' => 'section',
            'fields' => [
                [
                    'type' => 'mrkdwn',
                    'text' => 'FooText',
                ],
                [
                    'type' => 'plain_text',
                    'text' => 'BarText',
                ],
            ],
        ];

        $this->assertSame($expectedBlockArray, $block->toArray());
    }

    public function testAddTooManyFields(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Maximum number of fields should not exceed 10.');

        $block = new SlackSectionBlock();

        $block->field('FooText1')
            ->field('FooText2')
            ->field('FooText3')
            ->field('FooText4')
            ->field('FooText5')
            ->field('FooText6')
            ->field('FooText7')
            ->field('FooText8')
            ->field('FooText9')
            ->field('FooText10')
            ->field('FooText11')
        ;
    }
}
